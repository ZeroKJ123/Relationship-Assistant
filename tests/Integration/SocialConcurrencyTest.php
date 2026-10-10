<?php

declare(strict_types=1);
use App\Models\DateInvitation;
use App\Models\Friend;
use App\Models\GuestVisit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Support\AgreementScenario;
use Tests\Support\ConcurrentOperations;

uses(DatabaseMigrations::class);
it('creates a guest visit once for simultaneous identical submissions', function (): void {
    $pair = AgreementScenario::create();
    $friend = Friend::factory()->create(['user_id' => $pair->first->id]);
    $arguments = [(string) $pair->first->id, (string) $pair->couple->id, 'visit_create', (string) $friend->id, '710'];
    expect((new ConcurrentOperations)->run('agreement-worker.php', [$arguments, $arguments]))->toBe(['accepted', 'accepted']);
    expect(GuestVisit::query()->count())->toBe(1);
    expect(GuestVisit::query()->sole()->guests_snapshot[0]['name'])->toBe($friend->name);
});
it('creates a date once for concurrent retries', function (): void {
    $pair = AgreementScenario::create();
    $arguments = [(string) $pair->first->id, (string) $pair->couple->id, 'date_create', '711', '0'];
    expect((new ConcurrentOperations)->run('agreement-worker.php', [$arguments, $arguments]))->toBe(['accepted', 'accepted']);
    expect(DateInvitation::query()->count())->toBe(1);
});
it('allows only one competing friend edit with the same version', function (): void {
    $pair = AgreementScenario::create();
    $friend = Friend::factory()->create(['user_id' => $pair->first->id]);
    $arguments = [(string) $pair->first->id, (string) $pair->couple->id, 'friend_edit', (string) $friend->id];
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [[...$arguments, '712'], [...$arguments, '713']]);
    sort($results);
    expect($results)->toBe(['accepted', 'rejected']);
    expect($friend->refresh()->version)->toBe(1);
});
it('serializes archival with guest selection without changing a stored snapshot', function (): void {
    $pair = AgreementScenario::create();
    $friend = Friend::factory()->create(['user_id' => $pair->first->id]);
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $pair->first->id, (string) $pair->couple->id, 'visit_create', (string) $friend->id, '714'],
        [(string) $pair->first->id, (string) $pair->couple->id, 'friend_archive', (string) $friend->id, '0'],
    ]);
    expect($results[1])->toBe('accepted');
    expect($friend->refresh()->archived_at)->not->toBeNull();
    expect(GuestVisit::query()->count())->toBe($results[0] === 'accepted' ? 1 : 0);
    if ($results[0] === 'accepted') {
        expect(GuestVisit::query()->sole()->guests_snapshot[0]['name'])->toBe($friend->name);
    }
});
