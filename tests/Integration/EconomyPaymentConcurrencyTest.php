<?php

declare(strict_types=1);
use App\Actions\Duties\ReportDuty;
use App\Actions\Requests\CancelPartnerRequest;
use App\Actions\Requests\CreatePartnerRequest;
use App\Actions\Requests\RespondToRequest;
use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Enums\HeartOperationType;
use App\Enums\RequestResponse;
use App\Enums\RequestType;
use App\Enums\ReservationStatus;
use App\Models\EconomySettingsVersion;
use App\Models\HeartReservation;
use App\Services\Hearts\AvailableBalance;
use App\Services\Hearts\EconomySettingsStore;
use App\Services\Hearts\Ledger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Support\AgreementScenario;
use Tests\Support\ConcurrentOperations;

uses(DatabaseMigrations::class);
it('does not overwrite a competing administrator settings version', function (): void {
    $pair = AgreementScenario::create();
    $pair->first->forceFill(['is_admin' => true])->save();
    $pair->second->forceFill(['is_admin' => true])->save();
    app(EconomySettingsStore::class)->initialize();
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $pair->first->id, (string) $pair->couple->id, 'economy_edit', '1', '6'],
        [(string) $pair->second->id, (string) $pair->couple->id, 'economy_edit', '1', '7']]);
    sort($results);
    expect($results)->toBe(['accepted', 'rejected']);
    expect(EconomySettingsVersion::query()->count())->toBe(2);
    expect(app(EconomySettingsStore::class)->read()['reward'])->toBeIn([6, 7]);
});
it('reserves only one of two simultaneous agreements against the same available hearts', function (): void {
    $pair = AgreementScenario::create();
    $first = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(type: RequestType::Service, description: 'Первое', dueAt: CarbonImmutable::now()->addDays(3), heartPrice: 15));
    $second = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(type: RequestType::Service, description: 'Второе', dueAt: CarbonImmutable::now()->addDays(3), heartPrice: 15));
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $pair->second->id, (string) $pair->couple->id, 'request_agree', (string) $first->id, '0'],
        [(string) $pair->second->id, (string) $pair->couple->id, 'request_agree', (string) $second->id, '0']]);
    sort($results);
    expect($results)->toBe(['accepted', 'rejected']);
    expect(HeartReservation::query()->count())->toBe(1);
    expect(app(AvailableBalance::class)->get(app(Ledger::class)->getWallet($pair->first)))->toBe(5);
});
it('serializes settlement and a penalty while preserving the agreed reservation', function (): void {
    $pair = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(type: RequestType::Service, description: 'Услуга', dueAt: CarbonImmutable::now()->addDays(3), heartPrice: 15));
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Agree, 0));
    $duty = $request->duties()->sole();
    app(ReportDuty::class)->execute($pair->second, $pair->couple, $duty, version: 0, isDone: true);
    expect((new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $pair->first->id, (string) $pair->couple->id, 'duty_verify', (string) $duty->id, '1'],
        [(string) $pair->first->id, (string) $pair->couple->id, 'wallet_debit', '99', '30']]))->toBe(['accepted', 'accepted']);
    expect(app(Ledger::class)->getWallet($pair->first)->balance)->toBe(-25);
    expect(HeartReservation::query()->sole()->status)->toBe(ReservationStatus::Settled);
    app(Ledger::class)->reconcile($pair->first);
});
it('never both settles and releases competing cancellation and verification', function (): void {
    $pair = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(type: RequestType::Service, description: 'Услуга', dueAt: CarbonImmutable::now()->addDays(3), heartPrice: 15));
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Agree, 0));
    $duty = $request->duties()->sole();
    app(ReportDuty::class)->execute($pair->second, $pair->couple, $duty, version: 0, isDone: true);
    app(CancelPartnerRequest::class)->execute($pair->first, $pair->couple, $request, 1);
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $pair->first->id, (string) $pair->couple->id, 'duty_verify', (string) $duty->id, '1'],
        [(string) $pair->second->id, (string) $pair->couple->id, 'request_cancel', (string) $request->id, '2']]);
    sort($results);
    expect($results)->toBe(['accepted', 'rejected']);
    $reservation = HeartReservation::query()->sole();
    expect($reservation->status)->toBeIn([ReservationStatus::Settled, ReservationStatus::Released]);
    $wallet = app(Ledger::class)->getWallet($pair->first);
    expect($wallet->balance)->toBe($reservation->status === ReservationStatus::Settled ? 5 : 20);
    expect($wallet->transactions()->where('type', HeartOperationType::ServicePayment)->count())->toBe($reservation->status === ReservationStatus::Settled ? 1 : 0);
});
