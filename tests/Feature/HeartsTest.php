<?php

declare(strict_types=1);

use App\Actions\Couples\AcceptInvitation;
use App\Actions\Couples\Agreements\AcceptProposal;
use App\Actions\Couples\EndCouple;
use App\Actions\Couples\InvitePartner;
use App\Actions\Duties\ReportDuty;
use App\Actions\Duties\VerifyDuty;
use App\Actions\Hearts\AdjustPartnerHearts;
use App\Actions\Requests\CreatePartnerRequest;
use App\Actions\Requests\RespondToRequest;
use App\Data\Couples\CoupleSetupData;
use App\Data\Hearts\AdjustmentData;
use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Enums\DeclineReason;
use App\Enums\HeartOperationType;
use App\Enums\RequestResponse;
use App\Enums\RequestType;
use App\Models\HeartTransaction;
use App\Models\HeartWallet;
use App\Models\User;
use App\Services\Hearts\Ledger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\AgreementScenario;
use Tests\Support\DutyScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
});

it('gives the starting bonus once and keeps the wallet when changing partners', function (): void {
    $pair = AgreementScenario::create();
    $ledger = app(Ledger::class);
    $wallet = $ledger->getWallet($pair->first);
    expect($ledger->getWallet($pair->first)->id)->toBe($wallet->id);
    expect($wallet->balance)->toBe(20);
    app(EndCouple::class)->execute($pair->first, $pair->couple);
    $invite = app(InvitePartner::class)->execute($pair->first, new CoupleSetupData(CarbonImmutable::yesterday(), 'Europe/Minsk'));
    app(AcceptInvitation::class)->execute(User::factory()->create(), $invite->token);
    expect($ledger->getWallet($pair->first)->id)->toBe($wallet->id);
    expect($wallet->transactions()->where('type', HeartOperationType::StartingBonus)->count())->toBe(1);
    $ledger->reconcile($pair->first);
    $this->artisan('hearts:check-balances')->assertSuccessful();
});

it('applies integer operations idempotently, allows penalty debt and rejects changed retry payloads', function (): void {
    $owner = User::factory()->create();
    $ledger = app(Ledger::class);
    $entry = $ledger->apply($owner, 5, HeartOperationType::Reward, 'test:reward');
    expect($ledger->apply($owner, 5, HeartOperationType::Reward, 'test:reward')->id)->toBe($entry->id);
    expect(fn () => $ledger->apply($owner, 6, HeartOperationType::Reward, 'test:reward'))->toThrow(ValidationException::class);
    $ledger->apply($owner, -45, HeartOperationType::FalseReportPenalty, 'test:penalty');
    $wallet = $ledger->getWallet($owner);
    expect($wallet->balance)->toBe(-20);
    expect($wallet->transactions()->sum('amount'))->toBe(-20);
    $ledger->reconcile($owner);
    expect(fn () => $ledger->apply($owner, -5, HeartOperationType::Reward, 'test:invalid-sign'))->toThrow(LogicException::class);
});

it('rolls back the balance and history together and refuses editing or deleting ledger rows', function (): void {
    $owner = User::factory()->create();
    $ledger = app(Ledger::class);
    expect(fn () => DB::transaction(function () use ($owner, $ledger): void {
        $ledger->apply($owner, 5, HeartOperationType::Reward, 'test:rolled-back');
        throw new RuntimeException('Expected rollback');
    }))->toThrow(RuntimeException::class);
    expect(HeartWallet::query()->count())->toBe(0);
    expect(HeartTransaction::query()->count())->toBe(0);
    $entry = $ledger->apply($owner, 5, HeartOperationType::Reward, 'test:kept');
    expect(fn () => $entry->forceFill(['amount' => 99])->save())->toThrow(LogicException::class);
    expect(fn () => $entry->delete())->toThrow(LogicException::class);
    expect($entry->refresh()->amount)->toBe(5);
    expect($ledger->getWallet($owner)->balance)->toBe(25);
});

it('detects corruption instead of silently changing a balance to fit its history', function (): void {
    $owner = User::factory()->create();
    $wallet = app(Ledger::class)->getWallet($owner);
    $wallet->forceFill(['balance' => 21])->save();
    expect(fn () => app(Ledger::class)->reconcile($owner))->toThrow(LogicException::class);
    expect($wallet->refresh()->balance)->toBe(21);
});

it('partially awards confirmed work near the positive cap and records the actual reward', function (): void {
    config(['economy.starting_bonus' => 98]);
    $s = DutyScenario::create();
    app(ReportDuty::class)->execute($s->pair->second, $s->pair->couple, $s->duty, version: 0, isDone: true);
    app(VerifyDuty::class)->execute($s->pair->first, $s->pair->couple, $s->duty, version: 1, isConfirmed: true);
    $wallet = app(Ledger::class)->getWallet($s->pair->second);
    expect($wallet->balance)->toBe(100);
    expect($wallet->transactions()->where('type', HeartOperationType::Reward)->sole()->amount)->toBe(2);
    expect($wallet->transactions()->where('type', HeartOperationType::Reward)->sole()->requested_amount)->toBe(5);
});

it('enforces daily and weekly actual reward limits and resets them on Monday', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 09:00:00', 'UTC'));
    $pair = AgreementScenario::create();
    for ($day = 0; $day < 6; $day++) {
        for ($index = 0; $index < 4; $index++) {
            $s = DutyScenario::forPair($pair);
            app(ReportDuty::class)->execute($pair->second, $pair->couple, $s->duty, version: 0, isDone: true);
            app(VerifyDuty::class)->execute($pair->first, $pair->couple, $s->duty, version: 1, isConfirmed: true);
        }
        $total = app(Ledger::class)->getWallet($pair->second)->transactions()->where('type', HeartOperationType::Reward)->sum('amount');
        expect($total)->toBe(min(($day + 1) * 15, 75));
        $this->travel(1)->days();
    }
    $this->travel(1)->days();
    app(Ledger::class)->apply($pair->second, -50, HeartOperationType::FalseReportPenalty, 'test:make-cap-space');
    $s = DutyScenario::forPair($pair);
    app(ReportDuty::class)->execute($pair->second, $pair->couple, $s->duty, version: 0, isDone: true);
    app(VerifyDuty::class)->execute($pair->first, $pair->couple, $s->duty, version: 1, isConfirmed: true);
    expect(app(Ledger::class)->getWallet($pair->second)->transactions()->where('type', HeartOperationType::Reward)->sum('amount'))->toBe(80);
    $this->travelBack();
});

it('enforces partner-only manual daily and weekly limits, with a mandatory private reason', function (HeartOperationType $type, int $daily, int $weekly): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 09:00:00', 'UTC'));
    $pair = AgreementScenario::create();
    $adjust = app(AdjustPartnerHearts::class);
    $first = new AdjustmentData($type, $daily, 'Личная причина', Str::uuid()->toString());
    $entry = $adjust->execute($pair->first, $pair->couple, $first);
    expect($entry->heart_wallet_id)->toBe(app(Ledger::class)->getWallet($pair->second)->id);
    expect($adjust->execute($pair->first, $pair->couple, $first)->id)->toBe($entry->id);
    expect(DB::table('heart_transactions')->where('id', $entry->id)->value('reason'))->not->toBe('Личная причина');
    expect(fn () => $adjust->execute($pair->first, $pair->couple, new AdjustmentData($type, 1, 'Причина', Str::uuid()->toString())))->toThrow(ValidationException::class);
    $this->travel(1)->days();
    $adjust->execute($pair->first, $pair->couple, new AdjustmentData($type, $daily, 'Причина', Str::uuid()->toString()));
    $this->travel(1)->days();
    if ($weekly > 2 * $daily) {
        $adjust->execute($pair->first, $pair->couple, new AdjustmentData($type, $weekly - 2 * $daily, 'Причина', Str::uuid()->toString()));
    }
    expect(fn () => $adjust->execute($pair->first, $pair->couple, new AdjustmentData($type, 1, 'Причина', Str::uuid()->toString())))->toThrow(ValidationException::class);
    expect(fn () => $adjust->execute($pair->first, $pair->couple, new AdjustmentData($type, 1, '', Str::uuid()->toString())))->toThrow(ValidationException::class);
    $this->travelBack();
})->with([[HeartOperationType::ManualThanks, 25, 50], [HeartOperationType::ManualDeduction, 10, 25]]);

it('protects manual form ownership and confirmation and ignores an injected recipient', function (): void {
    $pair = AgreementScenario::create();
    $route = route('hearts.store', $pair->couple);
    $payload = ['kind' => 'thanks', 'amount' => 5, 'reason' => 'Спасибо', 'operation_key' => Str::uuid()->toString(), 'recipient_id' => $pair->first->id];
    $this->asUser($pair->first)->get(route('hearts.index'))->assertOk()->assertSee('Стартовые сердечки');
    $this->post($route, $payload)->assertSessionHasErrors('confirmation');
    $this->post($route, [...$payload, 'confirmation' => 1])->assertRedirect();
    $this->post($route, [...$payload, 'confirmation' => 1])->assertRedirect();
    expect(app(Ledger::class)->getWallet($pair->first)->balance)->toBe(20);
    expect(app(Ledger::class)->getWallet($pair->second)->balance)->toBe(25);
    $outsider = User::factory()->create(['is_admin' => true]);
    $this->asUser($outsider)->post($route, [...$payload, 'confirmation' => 1])->assertForbidden();
    $this->get(route('hearts.index'))->assertDontSee('Спасибо');
});

it('allows ordinary food at negative balance but rejects very wanted requests', function (): void {
    $pair = AgreementScenario::create();
    app(Ledger::class)->apply($pair->first, -30, HeartOperationType::FalseReportPenalty, 'test:negative');
    $proposal = $pair->answerBoth(3, 3);
    app(AcceptProposal::class)->execute($pair->first, $pair->couple, $proposal);
    app(AcceptProposal::class)->execute($pair->second, $pair->couple, $proposal);
    $ordinary = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Food, 'Суп', CarbonImmutable::now()->addDays(3)));
    expect($ordinary->is_very_wanted)->toBeFalse();
    expect(fn () => app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(type: RequestType::Food, description: 'Особенно хочу', dueAt: CarbonImmutable::now()->addDays(3), isVeryWanted: true)))->toThrow(ValidationException::class);
});

it('counts a very wanted request once and charges refusal once while exempting lack of food money', function (DeclineReason $reason, int $balance): void {
    $pair = AgreementScenario::create();
    $proposal = $pair->answerBoth(1, 1);
    app(AcceptProposal::class)->execute($pair->first, $pair->couple, $proposal);
    app(AcceptProposal::class)->execute($pair->second, $pair->couple, $proposal);
    $data = new NewRequestData(type: RequestType::Food, description: 'Суп', dueAt: CarbonImmutable::now()->addDays(3), isVeryWanted: true, operationKey: Str::uuid()->toString());
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, $data);
    expect(app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, $data)->id)->toBe($request->id);
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Decline, 0, $reason));
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Decline, 0, $reason));
    expect(app(Ledger::class)->getWallet($pair->second)->balance)->toBe($balance);
    expect(fn () => app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(type: RequestType::Food, description: 'Ещё суп', dueAt: CarbonImmutable::now()->addDays(3), isVeryWanted: true)))->toThrow(ValidationException::class);
})->with([[DeclineReason::Ordinary, 5], [DeclineReason::NoFoodMoney, 20]]);

it('resets the very wanted quota at Monday in the couple timezone', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-11 20:59:00', 'UTC'));
    $pair = AgreementScenario::create();
    $proposal = $pair->answerBoth(1, 1);
    app(AcceptProposal::class)->execute($pair->first, $pair->couple, $proposal);
    app(AcceptProposal::class)->execute($pair->second, $pair->couple, $proposal);
    app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(type: RequestType::Food, description: 'Суп', dueAt: CarbonImmutable::now()->addDays(3), isVeryWanted: true));
    $this->travel(2)->minutes();
    $second = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(type: RequestType::Food, description: 'Суп', dueAt: CarbonImmutable::now()->addDays(3), isVeryWanted: true));
    expect($second->id)->toBeGreaterThan(0);
    $this->travelBack();
});

it('keeps wallet and ledger factory fixtures reconciled without issuing extra starting bonuses', function (): void {
    $entry = HeartTransaction::factory()->create();
    $wallet = HeartWallet::query()->findOrFail($entry->heart_wallet_id);
    $owner = User::query()->findOrFail($wallet->user_id);
    app(Ledger::class)->reconcile($owner);
    expect($wallet->balance)->toBe(20);
    expect($wallet->transactions()->where('type', HeartOperationType::StartingBonus)->count())->toBe(1);
});
