<?php

declare(strict_types=1);
use App\Actions\Reminders\CancelReminder;
use App\Enums\ReminderStatus;
use App\Models\MoodEntry;
use App\Models\MoodPrompt;
use App\Models\PartnerReminder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Support\AgreementScenario;
use Tests\Support\ConcurrentOperations;

uses(DatabaseMigrations::class);
it('records a mood once for concurrent identical submissions', function (): void {
    $pair = AgreementScenario::create();
    $args = [(string) $pair->first->id, (string) $pair->couple->id, 'mood_record', '801', '0'];
    expect((new ConcurrentOperations)->run('agreement-worker.php', [$args, $args]))->toBe(['accepted', 'accepted']);
    expect(MoodEntry::query()->count())->toBe(1);
});
it('accepts only one competing answer to the same mood prompt', function (): void {
    $pair = AgreementScenario::create();
    $prompt = MoodPrompt::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->first->id]);
    $args = [(string) $pair->first->id, (string) $pair->couple->id, 'mood_prompt', (string) $prompt->id];
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [[...$args, '802'], [...$args, '803']]);
    sort($results);
    expect($results)->toBe(['accepted', 'rejected']);
    expect(MoodEntry::query()->count())->toBe(1);
});
it('creates a reminder once for concurrent retries', function (): void {
    $pair = AgreementScenario::create();
    $args = [(string) $pair->first->id, (string) $pair->couple->id, 'reminder_create', '804', '0'];
    expect((new ConcurrentOperations)->run('agreement-worker.php', [$args, $args]))->toBe(['accepted', 'accepted']);
    expect(PartnerReminder::query()->count())->toBe(1);
});
it('prepares a due reminder once for competing jobs', function (): void {
    $pair = AgreementScenario::create();
    $reminder = PartnerReminder::factory()->create(['couple_id' => $pair->couple->id, 'author_id' => $pair->first->id, 'recipient_id' => $pair->second->id, 'due_at' => CarbonImmutable::now()->addDays(5)->subHour()]);
    $args = [(string) $pair->first->id, (string) $pair->couple->id, 'reminder_prepare_late', (string) $reminder->id, '0'];
    expect((new ConcurrentOperations)->run('agreement-worker.php', [$args, $args]))->toBe(['accepted', 'accepted']);
    expect($reminder->refresh()->status)->toBe(ReminderStatus::Ready);
    expect($reminder->version)->toBe(1);
});
it('serializes cancellation with preparation and never revives a cancelled reminder', function (): void {
    $pair = AgreementScenario::create();
    $reminder = PartnerReminder::factory()->create(['couple_id' => $pair->couple->id, 'author_id' => $pair->first->id, 'recipient_id' => $pair->second->id, 'due_at' => CarbonImmutable::now()->addDays(5)->subHour()]);
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $pair->first->id, (string) $pair->couple->id, 'reminder_cancel', (string) $reminder->id, '0'],
        [(string) $pair->first->id, (string) $pair->couple->id, 'reminder_prepare_late', (string) $reminder->id, '0'],
    ]);
    expect($results[1])->toBe('accepted');
    $reminder->refresh();
    if ($results[0] === 'rejected') {
        app(CancelReminder::class)->execute($pair->first, $reminder, $reminder->version);
    }
    expect($reminder->refresh()->status)->toBe(ReminderStatus::Cancelled);
});
