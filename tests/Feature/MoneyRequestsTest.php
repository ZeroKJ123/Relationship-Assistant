<?php

declare(strict_types=1);

use App\Actions\Duties\ReportDuty;
use App\Actions\Duties\VerifyDuty;
use App\Actions\Money\CreateMoneyRequest;
use App\Actions\Profiles\UpdateProfile;
use App\Actions\Requests\CreatePartnerRequest;
use App\Actions\Requests\ResolveCondition;
use App\Actions\Requests\RespondToRequest;
use App\Data\Money\MoneyRequestData;
use App\Data\Profiles\UpdateProfileData;
use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Enums\ConditionType;
use App\Enums\Currency;
use App\Enums\DeclineReason;
use App\Enums\DutyKind;
use App\Enums\DutyStatus;
use App\Enums\Gender;
use App\Enums\RequestResponse;
use App\Enums\RequestType;
use App\Models\MoneyRequest;
use App\Models\RequestTemplate;
use App\Models\User;
use App\Services\Hearts\Ledger;
use App\Services\Money\DecimalAmount;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
});
it('keeps exact monetary amounts and currency without rounding', function (string $value, string $normalized): void {
    expect(app(DecimalAmount::class)->normalize($value))->toBe($normalized);
})->with([['0', '0.00'], ['0.01', '0.01'], ['12.3', '12.30'], ['999999.99', '999999.99'], ['1000000', '1000000.00']]);
it('rejects imprecise negative huge scientific and zero payment amounts', function (string $value): void {
    expect(fn () => app(DecimalAmount::class)->normalize($value, isPositive: true))->toThrow(ValidationException::class);
})->with(['0', '-1', '1.001', '1000000.01', '1e2', '1,20', '01.00', 'NaN']);
it('shares encrypted payment details only after acceptance and separates paying from receipt without rewards', function (): void {
    $pair = AgreementScenario::create();
    $pair->first->forceFill(['payment_details' => 'TEST recipient account'])->save();
    $data = new MoneyRequestData('12.30', Currency::Byn, 'Продукты', CarbonImmutable::now()->addDays(3), Str::uuid()->toString());
    $request = app(CreateMoneyRequest::class)->execute($pair->first, $pair->couple, $data);
    $money = $request->moneyRequest()->sole();
    expect(app(CreateMoneyRequest::class)->execute($pair->first, $pair->couple, $data)->id)->toBe($request->id);
    expect(MoneyRequest::query()->count())->toBe(1);
    $this->asUser($pair->second)->get(route('requests.show', [$pair->couple, $request]))->assertOk()->assertSee('12.30')->assertDontSee('TEST recipient account')->assertDontSee('Предложить встречное условие');
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Agree, 0));
    $this->get(route('requests.show', [$pair->couple, $request]))->assertOk()->assertSee('TEST recipient account');
    expect(DB::table('money_requests')->where('id', $money->id)->value('payment_details_snapshot'))->not->toBe('TEST recipient account');
    expect($money->refresh()->toArray())->not->toHaveKey('payment_details_snapshot');
    app(UpdateProfile::class)->execute($pair->first, $pair->first, new UpdateProfileData($pair->first->name, Gender::Unspecified, 'TEST changed account'));
    expect($money->refresh()->payment_details_snapshot)->toBe('TEST recipient account');
    $duty = $request->duties()->sole();
    expect($duty->is_reward_eligible)->toBeFalse();
    $this->get(route('duties.show', [$pair->couple, $duty]))->assertOk()->assertSee('Оплата отправлена');
    app(ReportDuty::class)->execute($pair->second, $pair->couple, $duty, 0, true);
    app(ReportDuty::class)->execute($pair->second, $pair->couple, $duty, 0, true);
    expect($duty->refresh()->status)->toBe(DutyStatus::Reported);
    expect($duty->reviewed_at)->toBeNull();
    $this->asUser($pair->first)->get(route('duties.show', [$pair->couple, $duty]))->assertOk()->assertSee('Деньги получены');
    app(VerifyDuty::class)->execute($pair->first, $pair->couple, $duty, 1, true);
    app(VerifyDuty::class)->execute($pair->first, $pair->couple, $duty, 1, true);
    expect($duty->refresh()->status)->toBe(DutyStatus::Confirmed);
    expect(app(Ledger::class)->getWallet($pair->second)->balance)->toBe(20);
    $this->asUser(User::factory()->create(['is_admin' => true]))->get(route('requests.show', [$pair->couple, $request]))->assertForbidden();
});
it('requires profile details and explicit confirmation and prevents changed duplicate payments', function (): void {
    $pair = AgreementScenario::create();
    $data = new MoneyRequestData('10', Currency::Rub, null, CarbonImmutable::now()->addDays(3), Str::uuid()->toString());
    expect(fn () => app(CreateMoneyRequest::class)->execute($pair->first, $pair->couple, $data))->toThrow(ValidationException::class);
    $pair->first->forceFill(['payment_details' => 'TEST details'])->save();
    $this->asUser($pair->first)->get(route('money.create', $pair->couple))->assertOk();
    $this->post(route('money.store', $pair->couple), ['amount' => '10.00', 'currency' => 'RUB', 'due_at' => $data->dueAt->setTimezone($pair->couple->timezone)->format('Y-m-d\TH:i'), 'operation_key' => $data->operationKey])->assertSessionHasErrors('confirmation');
    $request = app(CreateMoneyRequest::class)->execute($pair->first, $pair->couple, $data);
    expect(fn () => app(CreateMoneyRequest::class)->execute($pair->first, $pair->couple, new MoneyRequestData('11', Currency::Rub, null, $data->dueAt, $data->operationKey)))->toThrow(ValidationException::class);
    expect(fn () => app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(response: RequestResponse::Conditional, expectedVersion: 0, conditionType: ConditionType::Action, description: 'Другое', dueAt: $data->dueAt)))->toThrow(ValidationException::class);
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Decline, 0, DeclineReason::Ordinary));
    expect($request->duties()->count())->toBe(0);
    expect($request->moneyRequest()->sole()->payment_details_snapshot)->toBeNull();
});
it('supports monetary conditions with separate receipt confirmation and no action reward', function (): void {
    $pair = AgreementScenario::create();
    $pair->second->forceFill(['payment_details' => 'TEST condition account'])->save();
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(type: RequestType::Service, description: 'Услуга', dueAt: CarbonImmutable::now()->addDays(3)));
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(response: RequestResponse::Conditional, expectedVersion: 0, conditionType: ConditionType::Money, description: 'Оплата', dueAt: CarbonImmutable::now()->addDays(3), amount: '0.01', currency: Currency::Usd));
    $this->asUser($pair->first)->get(route('requests.show', [$pair->couple, $request]))->assertOk()->assertDontSee('TEST condition account');
    app(ResolveCondition::class)->execute($pair->first, $pair->couple, $request, 1);
    $condition = $request->conditions()->sole();
    expect($condition->amount)->toBe('0.01');
    expect($condition->payment_details_snapshot)->toBe('TEST condition account');
    $this->get(route('requests.show', [$pair->couple, $request]))->assertOk()->assertSee('TEST condition account');
    $duty = $request->duties()->where('kind', DutyKind::Condition)->sole();
    expect($duty->is_reward_eligible)->toBeFalse();
    app(ReportDuty::class)->execute($pair->first, $pair->couple, $duty, 0, true);
    app(VerifyDuty::class)->execute($pair->second, $pair->couple, $duty, 1, true);
    expect(app(Ledger::class)->getWallet($pair->first)->balance)->toBe(20);
    expect($request->duties()->where('kind', DutyKind::Service)->sole()->is_reward_eligible)->toBeTrue();
});

it('opens monetary catalog templates in the exact amount form and preserves their name', function (): void {
    $pair = AgreementScenario::create();
    $this->seed(CatalogSeeder::class);
    $pair->first->forceFill(['payment_details' => 'TEST template details'])->save();
    $template = RequestTemplate::query()->where('type', RequestType::Money)->firstOrFail();
    $this->asUser($pair->first)->get(route('requests.create', [$pair->couple, 'request_template_id' => $template->id]))->assertRedirect(route('money.create', [$pair->couple, 'request_template_id' => $template->id]));
    $this->get(route('money.create', [$pair->couple, 'request_template_id' => $template->id]))->assertOk()->assertSee($template->name);
    $this->post(route('money.store', $pair->couple), ['amount' => '0.01', 'currency' => 'EUR', 'purpose' => $template->name, 'request_template_id' => $template->id, 'due_at' => CarbonImmutable::now()->addDays(3)->format('Y-m-d\TH:i'), 'operation_key' => Str::uuid()->toString(), 'confirmation' => 1])->assertRedirect();
    $request = MoneyRequest::query()->sole()->partnerRequest;
    expect($request->template_name)->toBe($template->name);
    expect($request->moneyRequest->amount)->toBe('0.01');
});
