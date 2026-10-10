<?php

declare(strict_types=1);

use App\Actions\Couples\EndCouple;
use App\Actions\Duties\ReportDuty;
use App\Actions\Mood\SetMoodSchedule;
use App\Enums\DeliveryStatus;
use App\Enums\DutyStatus;
use App\Enums\NotificationKind;
use App\Enums\ReminderStatus;
use App\Models\Duty;
use App\Models\MoodPrompt;
use App\Models\NotificationDelivery;
use App\Models\PartnerReminder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);
afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('catches up only the latest available mood slot once in the couple timezone', function (string $time, string $timezone, ?string $expected): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse($time, 'UTC'));
    $pair = AgreementScenario::create();
    $pair->couple->forceFill(['timezone' => $timezone])->save();
    $pair->couple->members()->update(['joined_at' => CarbonImmutable::now()->subDays(2)]);
    $this->artisan('care:process-due')->assertSuccessful();
    $this->artisan('care:process-due')->assertSuccessful();
    if ($expected === null) {
        expect(MoodPrompt::query()->count())->toBe(0);

        return;
    }
    expect(MoodPrompt::query()->count())->toBe(2);
    expect(MoodPrompt::query()->firstOrFail()->scheduled_at->utc()->format('Y-m-d H:i'))->toBe($expected);
    expect(NotificationDelivery::query()->where('kind', NotificationKind::MoodPrompt)->count())->toBe(2);
})->with([
    ['2026-10-11 06:30', 'Europe/Minsk', '2026-10-11 06:00'],
    ['2026-10-11 05:59', 'Europe/Minsk', null],
    ['2026-10-11 09:00', 'Europe/Minsk', null],
    ['2026-10-11 15:00', 'Europe/Minsk', '2026-10-11 15:00'],
    ['2026-10-11 23:30', 'Asia/Tokyo', null],
    ['2026-10-12 00:15', 'Asia/Tokyo', '2026-10-12 00:00'],
    ['2027-03-28 07:30', 'Europe/Berlin', '2027-03-28 07:00'],
    ['2026-10-25 08:30', 'Europe/Berlin', '2026-10-25 08:00'],
]);

it('does not ask for a mood before joining after disabling or after ending the couple', function (string $case): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 06:30', 'UTC'));
    $pair = AgreementScenario::create();
    if ($case !== 'new_members') {
        $pair->couple->members()->update(['joined_at' => CarbonImmutable::now()->subDay()]);
    }
    if ($case === 'disabled') {
        app(SetMoodSchedule::class)->execute($pair->first, $pair->couple, false, 0);
        app(SetMoodSchedule::class)->execute($pair->second, $pair->couple, false, 0);
    }
    if ($case === 'ended') {
        app(EndCouple::class)->execute($pair->first, $pair->couple);
    }
    $this->artisan('care:process-due')->assertSuccessful();
    expect(MoodPrompt::query()->count())->toBe(0);
})->with(['new_members', 'disabled', 'ended']);

it('prepares due reminders once expires old ones and leaves future reminders scheduled', function (): void {
    $pair = AgreementScenario::create();
    $values = ['couple_id' => $pair->couple->id, 'author_id' => $pair->first->id, 'recipient_id' => $pair->second->id];
    $due = PartnerReminder::factory()->create([...$values, 'due_at' => CarbonImmutable::now()->subHour()]);
    $old = PartnerReminder::factory()->create([...$values, 'due_at' => CarbonImmutable::now()->subHours(25)]);
    $future = PartnerReminder::factory()->create([...$values, 'due_at' => CarbonImmutable::now()->addHour()]);
    $this->artisan('care:process-due')->assertSuccessful();
    $this->artisan('care:process-due')->assertSuccessful();
    expect($due->refresh()->status)->toBe(ReminderStatus::Ready);
    expect($old->refresh()->status)->toBe(ReminderStatus::Expired);
    expect($future->refresh()->status)->toBe(ReminderStatus::Scheduled);
    expect(NotificationDelivery::query()->where('kind', NotificationKind::Reminder)->count())->toBe(1);
});

it('notifies both partners of overdue duty once and never penalizes a timely report awaiting verification', function (): void {
    $pair = AgreementScenario::create();
    $values = ['couple_id' => $pair->couple->id, 'executor_id' => $pair->second->id, 'reviewer_id' => $pair->first->id, 'due_at' => CarbonImmutable::now()->addHour()];
    $late = Duty::factory()->create($values);
    $reported = Duty::factory()->create($values);
    app(ReportDuty::class)->execute($pair->second, $pair->couple, $reported, 0, true);
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addHours(2));
    $this->artisan('duties:check-overdue')->assertSuccessful();
    $this->artisan('duties:check-overdue')->assertSuccessful();
    expect($late->refresh()->status)->toBe(DutyStatus::Overdue);
    expect($reported->refresh()->status)->toBe(DutyStatus::Reported);
    expect($late->confirmations()->where('stage', 'overdue')->count())->toBe(1);
    expect($reported->confirmations()->where('stage', 'overdue')->count())->toBe(0);
    $event = $late->confirmations()->where('stage', 'overdue')->sole();
    $notifications = NotificationDelivery::query()->where('kind', NotificationKind::DutyEvent)->where('source_id', $event->id)->get();
    expect($notifications)->toHaveCount(2);
    expect($notifications->pluck('recipient_id')->sort()->values()->all())->toBe(collect([$pair->first->id, $pair->second->id])->sort()->values()->all());
    expect($notifications->pluck('status')->all())->toBe([DeliveryStatus::Pending, DeliveryStatus::Pending]);
});
