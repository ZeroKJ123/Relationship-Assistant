<?php

declare(strict_types=1);

use App\Actions\Couples\Agreements\AcceptProposal;
use App\Actions\Hearts\AdjustPartnerHearts;
use App\Data\Hearts\AdjustmentData;
use App\Enums\HeartOperationType;
use App\Models\PartnerRequest;
use App\Services\Hearts\Ledger;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\Support\AgreementScenario;
use Tests\Support\ConcurrentOperations;

uses(DatabaseMigrations::class);

it('keeps concurrent credits and debits consistent with immutable history', function (): void {
    $s = AgreementScenario::create();
    $worker = new ConcurrentOperations;
    expect($worker->run('agreement-worker.php', [
        [(string) $s->first->id, (string) $s->couple->id, 'wallet_credit', '1', '5'],
        [(string) $s->first->id, (string) $s->couple->id, 'wallet_credit', '2', '5'],
    ]))->toBe(['accepted', 'accepted']);
    expect(app(Ledger::class)->getWallet($s->first)->balance)->toBe(30);
    expect($worker->run('agreement-worker.php', [
        [(string) $s->first->id, (string) $s->couple->id, 'wallet_debit', '3', '25'],
        [(string) $s->first->id, (string) $s->couple->id, 'wallet_debit', '4', '25'],
    ]))->toBe(['accepted', 'accepted']);
    expect(app(Ledger::class)->getWallet($s->first)->balance)->toBe(-20);
    app(Ledger::class)->reconcile($s->first);
});

it('initializes independent wallets concurrently without duplicate starting bonuses', function (): void {
    $s = AgreementScenario::create();
    expect((new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $s->first->id, (string) $s->couple->id, 'wallet_credit', '1', '5'],
        [(string) $s->second->id, (string) $s->couple->id, 'wallet_credit', '2', '5'],
    ]))->toBe(['accepted', 'accepted']);
    foreach ([$s->first, $s->second] as $user) {
        $wallet = app(Ledger::class)->getWallet($user);
        expect($wallet->balance)->toBe(25);
        expect($wallet->transactions()->where('type', HeartOperationType::StartingBonus)->count())->toBe(1);
    }
});

it('deduplicates concurrent copies of the same ledger operation', function (): void {
    $s = AgreementScenario::create();
    $attempt = [(string) $s->first->id, (string) $s->couple->id, 'wallet_credit', '1', '5'];
    expect((new ConcurrentOperations)->run('agreement-worker.php', [$attempt, $attempt]))->toBe(['accepted', 'accepted']);
    $wallet = app(Ledger::class)->getWallet($s->first);
    expect($wallet->balance)->toBe(25);
    expect($wallet->transactions()->count())->toBe(2);
});

it('spends the final manual daily allowance only once', function (): void {
    $s = AgreementScenario::create();
    app(AdjustPartnerHearts::class)->execute($s->first, $s->couple, new AdjustmentData(HeartOperationType::ManualThanks, 20, 'Test reason', Str::uuid()->toString()));
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $s->first->id, (string) $s->couple->id, 'manual_thanks', '1', '5'],
        [(string) $s->first->id, (string) $s->couple->id, 'manual_thanks', '2', '5'],
    ]);
    sort($results);
    expect($results)->toBe(['accepted', 'rejected']);
    expect(app(Ledger::class)->getWallet($s->second)->balance)->toBe(45);
});

it('spends one remaining very wanted slot only once', function (): void {
    $s = AgreementScenario::create();
    $proposal = $s->answerBoth(1, 1);
    app(AcceptProposal::class)->execute($s->first, $s->couple, $proposal);
    app(AcceptProposal::class)->execute($s->second, $s->couple, $proposal);
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $s->first->id, (string) $s->couple->id, 'very_wanted', '1', '0'],
        [(string) $s->first->id, (string) $s->couple->id, 'very_wanted', '2', '0'],
    ]);
    sort($results);
    expect($results)->toBe(['accepted', 'rejected']);
    expect(PartnerRequest::query()->where('is_very_wanted', true)->count())->toBe(1);
});
