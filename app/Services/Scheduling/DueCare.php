<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Actions\Duties\CreateDutyReminders;
use App\Actions\Mood\CreateMoodPrompt;
use App\Actions\Reminders\PrepareReminder;
use App\Enums\CoupleStatus;
use App\Enums\ReminderStatus;
use App\Models\CoupleMember;
use App\Models\MoodPrompt;
use App\Models\PartnerReminder;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class DueCare
{
    private const CHUNK_SIZE = 100;

    public function __construct(private readonly CreateMoodPrompt $prompts, private readonly PrepareReminder $reminders, private readonly CreateDutyReminders $duties) {}

    public function execute(): void
    {
        $this->processMoods();
        $this->processReminders();
        $this->processDuties();
    }

    private function processMoods(): void
    {
        CoupleMember::query()->whereNotNull('active_user_id')->where('mood_schedule_enabled', true)->whereRelation('couple', 'status', CoupleStatus::Active)->with(['couple', 'user'])->lazyById(self::CHUNK_SIZE)->each(function (CoupleMember $member): void {
            $couple = $member->couple;
            $user = $member->user;
            if ($couple === null || $user === null) {
                Log::error('schedule.member_unavailable', ['member_id' => $member->id]);

                return;
            }
            $slot = $this->getLatestSlot(CarbonImmutable::now(), $couple->timezone);
            if ($slot === null || $slot->lessThan($member->joined_at)) {
                return;
            }
            try {
                $this->prompts->execute($user, $couple, $slot);
            } catch (AuthorizationException|ValidationException) {
                Log::warning('schedule.mood_skipped', ['member_id' => $member->id, 'reason' => 'couple_changed']);
            }
        });
    }

    private function processDuties(): void
    {
        CoupleMember::query()->whereNotNull('active_user_id')->whereRelation('couple', 'status', CoupleStatus::Active)->with(['couple', 'user'])->lazyById(self::CHUNK_SIZE)->each(function (CoupleMember $member): void {
            $couple = $member->couple;
            $user = $member->user;
            if ($couple === null || $user === null) {
                Log::error('schedule.member_unavailable', ['member_id' => $member->id]);

                return;
            }
            $local = CarbonImmutable::now($couple->timezone);
            if (! in_array($local->hour, CreateDutyReminders::HOURS, true) || $local->minute >= CreateDutyReminders::WINDOW_MINUTES) {
                return;
            }
            $slot = $local->startOfHour()->utc();
            if ($slot->lessThan($member->joined_at)) {
                return;
            }
            try {
                $this->duties->execute($user, $couple, $slot);
            } catch (AuthorizationException|ValidationException) {
                Log::warning('schedule.duty_skipped', ['member_id' => $member->id, 'reason' => 'couple_changed']);
            }
        });
    }

    private function processReminders(): void
    {
        PartnerReminder::query()->where('status', ReminderStatus::Scheduled)->where('due_at', '<=', now())->lazyById(self::CHUNK_SIZE)->each(function (PartnerReminder $reminder): void {
            $this->reminders->execute($reminder, $reminder->version);
        });
    }

    private function getLatestSlot(CarbonImmutable $now, string $timezone): ?CarbonImmutable
    {
        $local = $now->setTimezone($timezone);
        $day = $local->startOfDay();
        $latest = null;
        foreach (MoodPrompt::HOURS as $hour) {
            $candidate = $day->setTime($hour, 0)->utc();
            if ($candidate->lessThanOrEqualTo($now)) {
                $latest = $candidate;
            }
        }
        if ($latest === null || $latest->addHours(MoodPrompt::RESPONSE_WINDOW_HOURS)->lessThanOrEqualTo($now)) {
            return null;
        }

        return $latest;
    }
}
