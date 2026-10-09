<?php

declare(strict_types=1);

use App\Actions\Duties\ReportDuty;
use App\Actions\Duties\VerifyDuty;
use App\Enums\DutyStatus;
use App\Enums\HeartOperationType;
use App\Services\Hearts\Ledger;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Support\ConcurrentOperations;
use Tests\Support\DutyScenario;

uses(DatabaseMigrations::class);

it('records one report when the executor confirms concurrently', function (): void {
    $s = DutyScenario::create();
    $attempt = [(string) $s->pair->second->id, (string) $s->pair->couple->id, 'duty_report', (string) $s->duty->id, '0'];
    expect((new ConcurrentOperations)->run('agreement-worker.php', [$attempt, $attempt]))->toBe(['accepted', 'accepted']);
    expect($s->duty->refresh()->status)->toBe(DutyStatus::Reported);
    expect($s->duty->confirmations()->count())->toBe(1);
});

it('awards confirmed work once when verification arrives concurrently', function (): void {
    $s = DutyScenario::create();
    app(ReportDuty::class)->execute($s->pair->second, $s->pair->couple, $s->duty, version: 0, isDone: true);
    $attempt = [(string) $s->pair->first->id, (string) $s->pair->couple->id, 'duty_verify', (string) $s->duty->id, '1'];
    expect((new ConcurrentOperations)->run('agreement-worker.php', [$attempt, $attempt]))->toBe(['accepted', 'accepted']);
    $wallet = app(Ledger::class)->getWallet($s->pair->second);
    expect($wallet->balance)->toBe(25);
    expect($wallet->transactions()->where('type', HeartOperationType::Reward)->count())->toBe(1);
    expect($s->duty->refresh()->status)->toBe(DutyStatus::Confirmed);
});

it('never both awards and penalizes competing verification decisions', function (): void {
    $s = DutyScenario::create();
    app(ReportDuty::class)->execute($s->pair->second, $s->pair->couple, $s->duty, version: 0, isDone: true);
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $s->pair->first->id, (string) $s->pair->couple->id, 'duty_verify', (string) $s->duty->id, '1'],
        [(string) $s->pair->first->id, (string) $s->pair->couple->id, 'duty_dispute', (string) $s->duty->id, '1'],
    ]);
    sort($results);
    expect($results)->toBe(['accepted', 'rejected']);
    $wallet = app(Ledger::class)->getWallet($s->pair->second);
    expect($wallet->transactions()->whereIn('type', [HeartOperationType::Reward, HeartOperationType::FalseReportPenalty])->count())->toBe(1);
    expect($wallet->balance)->toBeIn([5, 25]);
});

it('serializes a late report against the automatic overdue check without double charging', function (): void {
    $s = DutyScenario::create();
    (new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $s->pair->second->id, (string) $s->pair->couple->id, 'duty_report_late', (string) $s->duty->id, '0'],
        [(string) $s->pair->second->id, (string) $s->pair->couple->id, 'duty_overdue_late', (string) $s->duty->id, '0'],
    ]);
    $wallet = app(Ledger::class)->getWallet($s->pair->second);
    expect($wallet->balance)->toBe(10);
    expect($wallet->transactions()->where('type', HeartOperationType::OverduePenalty)->count())->toBe(1);
    expect($s->duty->refresh()->status)->toBeIn([DutyStatus::Reported, DutyStatus::Overdue]);
});

it('confirms both duties but does not overspend the remaining daily reward allowance', function (): void {
    $s = DutyScenario::create();
    for ($index = 0; $index < 2; $index++) {
        $previous = DutyScenario::forPair($s->pair);
        app(ReportDuty::class)->execute($s->pair->second, $s->pair->couple, $previous->duty, version: 0, isDone: true);
        app(VerifyDuty::class)->execute($s->pair->first, $s->pair->couple, $previous->duty, version: 1, isConfirmed: true);
    }
    $other = DutyScenario::forPair($s->pair);
    foreach ([$s->duty, $other->duty] as $duty) {
        app(ReportDuty::class)->execute($s->pair->second, $s->pair->couple, $duty, version: 0, isDone: true);
    }
    expect((new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $s->pair->first->id, (string) $s->pair->couple->id, 'duty_verify', (string) $s->duty->id, '1'],
        [(string) $s->pair->first->id, (string) $s->pair->couple->id, 'duty_verify', (string) $other->duty->id, '1'],
    ]))->toBe(['accepted', 'accepted']);
    expect(app(Ledger::class)->getWallet($s->pair->second)->balance)->toBe(35);
    expect($s->duty->refresh()->status)->toBe(DutyStatus::Confirmed);
    expect($other->duty->refresh()->status)->toBe(DutyStatus::Confirmed);
});
