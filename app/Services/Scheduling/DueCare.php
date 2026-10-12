<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Actions\Couples\CreateDateNotices;
use App\Actions\Duties\CreateDutyReminders;
use App\Actions\Mood\CreateMoodPrompt;
use App\Actions\Reminders\PrepareReminder;
use App\Enums\CoupleStatus;
use App\Enums\ReminderStatus;
use App\Models\CoupleMember;
use App\Models\PartnerReminder;
use App\Services\Mood\MoodSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class DueCare
{
    private const CHUNK_SIZE = 100;

    public function __construct(private readonly CreateMoodPrompt $prompts, private readonly PrepareReminder $reminders, private readonly CreateDutyReminders $duties, private readonly MoodSchedule $schedule, private readonly CreateDateNotices $dates) {}

    public function execute(): void
    {
        $this->processMoods();
        $this->processReminders();
        $this->processDuties();
        $this->processDates();
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
            $slot = $this->schedule->getLatestSlot($member, CarbonImmutable::now(), $couple->timezone);
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

    private function processDates(): void
    {
        CoupleMember::query()->whereNotNull('active_user_id')->whereRelation('couple', 'status', CoupleStatus::Active)->with(['couple', 'user'])->lazyById(self::CHUNK_SIZE)->each(function (CoupleMember $member): void {
            $couple = $member->couple;
            $user = $member->user;
            if ($couple === null || $user === null) {
                Log::error('schedule.member_unavailable', ['member_id' => $member->id]);

                return;
            }
            $local = CarbonImmutable::now($couple->timezone);
            if ($local->hour !== CreateDateNotices::HOUR || $local->minute >= CreateDateNotices::WINDOW_MINUTES) {
                return;
            }
            try {
                $this->dates->execute($user, $couple, $local->startOfHour()->utc());
            } catch (AuthorizationException|ValidationException) {
                Log::warning('schedule.date_skipped', ['member_id' => $member->id, 'reason' => 'couple_changed']);
            }
        });
    }
}
