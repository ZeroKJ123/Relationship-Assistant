<?php

declare(strict_types=1);

use App\Actions\Couples\Agreements\AcceptProposal;
use App\Enums\AgreementProposalStatus;
use App\Enums\CoupleStatus;
use App\Models\AgreementApproval;
use App\Services\Couples\WeeklyLimit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Support\AgreementScenario;
use Tests\Support\ConcurrentOperations;

uses(DatabaseMigrations::class);

it('activates one version when both partners approve it concurrently', function (): void {
    $scenario = AgreementScenario::create();
    $proposal = $scenario->answerBoth(3, 4);
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $scenario->first->id, (string) $scenario->couple->id, 'accept', (string) $proposal->id, '0'],
        [(string) $scenario->second->id, (string) $scenario->couple->id, 'accept', (string) $proposal->id, '0'],
    ]);
    expect($results)->toBe(['accepted', 'accepted']);
    expect($proposal->refresh()->status)->toBe(AgreementProposalStatus::Accepted);
    expect($proposal->approvals()->count())->toBe(2);
    expect(app(WeeklyLimit::class)->getActiveLimit($scenario->couple))->toBe(4);
});

it('keeps repeated concurrent approval by one partner idempotent', function (): void {
    $scenario = AgreementScenario::create();
    $proposal = $scenario->answerBoth(3, 4);
    $attempt = [(string) $scenario->first->id, (string) $scenario->couple->id, 'accept', (string) $proposal->id, '0'];
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [$attempt, $attempt]);
    expect($results)->toBe(['accepted', 'accepted']);
    expect(AgreementApproval::query()->count())->toBe(1);
    expect(app(WeeklyLimit::class)->getActiveLimit($scenario->couple))->toBeNull();
});

it('accepts only one of two competing counteroffers based on the same version', function (): void {
    $scenario = AgreementScenario::create();
    $scenario->answerBoth(3, 4);
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $scenario->first->id, (string) $scenario->couple->id, 'propose', '1', '6'],
        [(string) $scenario->second->id, (string) $scenario->couple->id, 'propose', '1', '7'],
    ]);
    sort($results);
    expect($results)->toBe(['accepted', 'rejected']);
    $agreement = $scenario->couple->agreement()->firstOrFail();
    expect($agreement->current_version)->toBe(2);
    expect($agreement->proposals()->count())->toBe(2);
    expect($agreement->active_weekly_limit)->toBeNull();
});

it('never applies an old approval to a simultaneous counteroffer', function (): void {
    $scenario = AgreementScenario::create();
    $proposal = $scenario->answerBoth(3, 4);
    (new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $scenario->first->id, (string) $scenario->couple->id, 'accept', (string) $proposal->id, '0'],
        [(string) $scenario->second->id, (string) $scenario->couple->id, 'propose', '1', '6'],
    ]);
    $agreement = $scenario->couple->agreement()->firstOrFail();
    expect($agreement->current_version)->toBe(2);
    expect($agreement->proposals()->where('version', 2)->sole()->approvals()->count())->toBe(0);
    expect($proposal->refresh()->status)->toBe(AgreementProposalStatus::Superseded);
    expect(app(WeeklyLimit::class)->getActiveLimit($scenario->couple))->toBeNull();
});

it('serializes final approval against ending the couple without a deadlock', function (): void {
    $scenario = AgreementScenario::create();
    $proposal = $scenario->answerBoth(3, 4);
    app(AcceptProposal::class)->execute($scenario->first, $scenario->couple, $proposal);
    (new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $scenario->first->id, (string) $scenario->couple->id, 'end', '0', '0'],
        [(string) $scenario->second->id, (string) $scenario->couple->id, 'accept', (string) $proposal->id, '0'],
    ]);
    expect($scenario->couple->refresh()->status)->toBe(CoupleStatus::Ended);
    expect(app(WeeklyLimit::class)->getActiveLimit($scenario->couple))->toBeNull();
});

it('creates the compromise when both initial answers arrive concurrently', function (): void {
    $scenario = AgreementScenario::create();
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $scenario->first->id, (string) $scenario->couple->id, 'answer', '0', '3'],
        [(string) $scenario->second->id, (string) $scenario->couple->id, 'answer', '0', '4'],
    ]);
    expect($results)->toBe(['accepted', 'accepted']);
    $agreement = $scenario->couple->agreement()->firstOrFail();
    expect($agreement->answers()->count())->toBe(2);
    expect($agreement->proposals()->sole()->weekly_limit)->toBe(4);
    expect($agreement->active_weekly_limit)->toBeNull();
});
