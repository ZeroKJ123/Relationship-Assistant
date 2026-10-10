<?php

declare(strict_types=1);

use App\Actions\Reminders\PrepareReminder;
use App\Enums\DeliveryStatus;
use App\Enums\NotificationKind;
use App\Enums\ReminderStatus;
use App\Models\MoodPrompt;
use App\Models\NotificationDelivery;
use App\Models\PartnerReminder;
use App\Models\TelegramAccount;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Support\AgreementScenario;
use Tests\Support\ConcurrentOperations;

uses(DatabaseMigrations::class);

it('claims a delivery once when two workers compete', function (): void {
    $pair = AgreementScenario::create();
    TelegramAccount::factory()->create(['user_id' => $pair->second->id]);
    $reminder = PartnerReminder::factory()->create(['couple_id' => $pair->couple->id, 'author_id' => $pair->first->id, 'recipient_id' => $pair->second->id, 'due_at' => CarbonImmutable::now()->subMinute()]);
    app(PrepareReminder::class)->execute($reminder, 0);
    $delivery = NotificationDelivery::query()->sole();
    $attempt = [(string) $pair->second->id, (string) $pair->couple->id, 'notification_claim', (string) $delivery->id, '0'];
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [$attempt, $attempt]);
    sort($results);
    expect($results)->toBe(['accepted', 'rejected']);
    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Processing);
    expect($delivery->attempts)->toBe(1);
    expect($reminder->refresh()->status)->toBe(ReminderStatus::Sending);
});

it('serializes cancellation against transport handoff', function (): void {
    $pair = AgreementScenario::create();
    TelegramAccount::factory()->create(['user_id' => $pair->second->id]);
    $reminder = PartnerReminder::factory()->create(['couple_id' => $pair->couple->id, 'author_id' => $pair->first->id, 'recipient_id' => $pair->second->id, 'due_at' => CarbonImmutable::now()->subMinute()]);
    app(PrepareReminder::class)->execute($reminder, 0);
    $delivery = NotificationDelivery::query()->sole();
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $pair->second->id, (string) $pair->couple->id, 'notification_claim', (string) $delivery->id, '0'],
        [(string) $pair->first->id, (string) $pair->couple->id, 'reminder_cancel', (string) $reminder->id, '1'],
    ]);
    sort($results);
    expect($results)->toBe(['accepted', 'rejected']);
    $delivery->refresh();
    $reminder->refresh();
    expect($reminder->status)->toBeIn([ReminderStatus::Sending, ReminderStatus::Cancelled]);
    expect($delivery->attempts)->toBe($reminder->status === ReminderStatus::Sending ? 1 : 0);
    expect($delivery->status)->toBe($reminder->status === ReminderStatus::Sending ? DeliveryStatus::Processing : DeliveryStatus::Cancelled);
});

it('creates only one prompt and notification per partner under concurrent schedule scans', function (): void {
    $pair = AgreementScenario::create();
    $scheduled = CarbonImmutable::parse('2026-10-12 06:30', 'UTC');
    $pair->couple->members()->update(['joined_at' => $scheduled->subDay()]);
    $attempt = [(string) $pair->first->id, (string) $pair->couple->id, 'care_process', '0', (string) $scheduled->timestamp];
    expect((new ConcurrentOperations)->run('agreement-worker.php', [$attempt, $attempt]))->toBe(['accepted', 'accepted']);
    expect(MoodPrompt::query()->count())->toBe(2);
    expect(NotificationDelivery::query()->where('kind', NotificationKind::MoodPrompt)->count())->toBe(2);
});
