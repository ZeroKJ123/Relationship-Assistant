<?php

declare(strict_types=1);

namespace App\Actions\Couples;

use App\Enums\NotificationKind;
use App\Models\Couple;
use App\Models\MemorableDateNotice;
use App\Models\User;
use App\Services\Couples\MemorableDates;
use App\Services\Notifications\NotificationOutbox;
use App\Services\Requests\RequestContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CreateDateNotices
{
    /** Час по времени пары, когда приходят напоминания о датах. */
    public const HOUR = 9;

    public const WINDOW_MINUTES = 30;

    /** За сколько дней до даты напоминать; 0 — в сам день. */
    public const DAYS_BEFORE = [3, 0];

    private const LIFETIME_HOURS = 12;

    public function __construct(private readonly RequestContext $context, private readonly NotificationOutbox $outbox, private readonly MemorableDates $dates) {}

    public function execute(User $actor, Couple $couple, CarbonImmutable $slot): void
    {
        DB::transaction(function () use ($actor, $couple, $slot): void {
            $current = $this->context->lockCouple($actor, $couple);
            $local = $slot->setTimezone($current->timezone);
            if ($local->hour !== self::HOUR || $local->minute !== 0 || $slot->isFuture() || $slot->addMinutes(self::WINDOW_MINUTES)->lessThanOrEqualTo(CarbonImmutable::now())) {
                return;
            }
            foreach ($this->dates->getUpcoming($current, $slot) as $date) {
                if (! in_array($date->daysLeft, self::DAYS_BEFORE, true)) {
                    continue;
                }
                $occursOn = $date->date->toDateString();
                if (MemorableDateNotice::query()->where('user_id', $actor->id)->where('source_key', $date->key)->whereDate('occurs_on', $occursOn)->where('days_before', $date->daysLeft)->lockForUpdate()->first() !== null) {
                    continue;
                }
                $notice = new MemorableDateNotice;
                $notice->forceFill(['user_id' => $actor->id, 'source_key' => $date->key, 'occurs_on' => $occursOn, 'days_before' => $date->daysLeft, 'couple_id' => $current->id, 'title' => $date->title, 'years' => $date->years])->save();
                $this->outbox->record($current->id, $actor->id, NotificationKind::MemorableDate, $notice->id, expiresAt: $slot->addHours(self::LIFETIME_HOURS));
            }
        }, attempts: 3);
    }
}
