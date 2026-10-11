<?php

declare(strict_types=1);
use App\Actions\Catalogs\CreateSettlementMethod;
use App\Actions\Requests\CreatePartnerRequest;
use App\Actions\Requests\ResolveCondition;
use App\Actions\Requests\RespondToRequest;
use App\Data\Catalogs\SettlementMethodData;
use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Enums\ConditionType;
use App\Enums\RequestResponse;
use App\Enums\RequestType;
use App\Models\Duty;
use App\Models\ExpenseCategory;
use App\Models\HeartTransaction;
use App\Models\RequestCategory;
use App\Models\RequestCondition;
use App\Models\RequestTemplate;
use App\Models\SettlementMethod;
use App\Models\User;
use App\Services\Catalogs\CatalogDefinitions;
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
it('seeds the agreed catalogs idempotently without creating duties or heart operations', function (): void {
    $this->seed(CatalogSeeder::class);
    $ids = RequestTemplate::query()->pluck('id')->all();
    $this->seed(CatalogSeeder::class);
    expect(RequestTemplate::query()->pluck('id')->all())->toBe($ids);
    expect(ExpenseCategory::query()->count())->toBe(8);
    expect(RequestCategory::query()->count())->toBe(8);
    expect(RequestTemplate::query()->count())->toBe(count(app(CatalogDefinitions::class)->templates()));
    expect(SettlementMethod::query()->count())->toBe(7);
    expect(SettlementMethod::query()->where('code', 'sushi')->sole()->options)->toBe(['small', 'medium', 'large']);
    expect(Duty::query()->count())->toBe(0);
    expect(HeartTransaction::query()->count())->toBe(0);
    $pair = AgreementScenario::create();
    $this->asUser($pair->first)->get(route('catalogs.index'))->assertOk()->assertSee('Транспорт и поездки');
    $this->get(route('catalogs.index', ['templates_page' => 2]))->assertOk()->assertDontSee('Посмотреть все присланные TikTok');
});
it('hides legacy templates while preserving catalog snapshots against later renames', function (): void {
    $this->seed(CatalogSeeder::class);
    $pair = AgreementScenario::create();
    $template = RequestTemplate::query()->where('code', 'watch-tiktok')->sole();
    $this->asUser($pair->first)->get(route('requests.create', ['couple' => $pair->couple, 'request_template_id' => $template->id]))->assertOk()->assertDontSee('Посмотреть все присланные TikTok');
    $data = new NewRequestData(type: RequestType::Service, description: 'Видео за эту неделю', dueAt: CarbonImmutable::now()->addDays(3), templateId: $template->id);
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, $data);
    expect($request->template_name)->toBe('Посмотреть все присланные TikTok');
    expect($request->category_name)->toBe('Совместное время');
    expect(Duty::query()->count())->toBe(0);
    $template->forceFill(['name' => 'Другое название'])->save();
    expect($request->refresh()->template_name)->toBe('Посмотреть все присланные TikTok');
    expect(fn () => app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(type: RequestType::Money, description: 'Ошибка вида', dueAt: CarbonImmutable::now()->addDays(3), templateId: $template->id)))->toThrow(ValidationException::class);
    expect(fn () => app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(type: RequestType::Service, description: 'Ошибка ID', dueAt: CarbonImmutable::now()->addDays(3), templateId: 9999)))->toThrow(ValidationException::class);
});
it('keeps custom methods private and deduplicates their creation', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create(['is_admin' => true]);
    $key = Str::uuid()->toString();
    $action = app(CreateSettlementMethod::class);
    $method = $action->execute($owner, new SettlementMethodData('Личный способ', 'Личные детали', $key));
    expect($action->execute($owner, new SettlementMethodData('Личный способ', 'Личные детали', $key))->id)->toBe($method->id);
    expect(DB::table('settlement_methods')->where('id', $method->id)->value('name'))->not->toBe('Личный способ');
    expect(fn () => $action->execute($other, new SettlementMethodData('Личный способ', 'Личные детали', $key)))->toThrow(ValidationException::class);
    $this->asUser($owner)->get(route('catalogs.index'))->assertSee('Личный способ');
    $this->asUser($other)->get(route('catalogs.index'))->assertDontSee('Личный способ')->assertDontSee('Личные детали');
    $this->asUser($owner)->post(route('catalogs.methods.store'), ['name' => 'Ещё способ', 'details' => 'Описание', 'operation_key' => Str::uuid()->toString(), 'user_id' => $other->id])->assertRedirect();
    expect(SettlementMethod::query()->where('user_id', $owner->id)->count())->toBe(2);
});
it('saves each sushi size as an explicit condition snapshot and creates no automatic obligation before acceptance', function (string $size): void {
    $this->seed(CatalogSeeder::class);
    $pair = AgreementScenario::create();
    $method = SettlementMethod::query()->where('code', 'sushi')->sole();
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Guests, 'Гости', CarbonImmutable::now()->addDays(3)));
    $route = route('requests.responses.store', [$pair->couple, $request]);
    $this->asUser($pair->second)->get(route('requests.show', [$pair->couple, $request]))->assertOk()->assertSee('Размер суши');
    $this->post($route, ['response' => 'conditional', 'expected_version' => 0, 'condition_type' => 'custom', 'description' => 'Заказать к вечеру', 'due_at' => CarbonImmutable::now()->addDays(3)->format('Y-m-d\TH:i'), 'settlement_method_id' => $method->id, 'sushi_size' => $size, 'confirmation' => 1])->assertRedirect();
    $condition = $request->conditions()->sole();
    expect($condition->sushi_size)->toBe($size);
    expect($condition->method_name)->toBe('Заказать суши');
    expect(Duty::query()->count())->toBe(0);
    $method->forceFill(['name' => 'Новое имя'])->save();
    app(ResolveCondition::class)->execute($pair->first, $pair->couple, $request, 1);
    expect(Duty::query()->count())->toBe(2);
    expect($condition->refresh()->method_name)->toBe('Заказать суши');
})->with(['small', 'medium', 'large']);
it('rejects a missing sushi size, a foreign private method and financial methods awaiting their own flow', function (): void {
    $this->seed(CatalogSeeder::class);
    $pair = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Service, 'Дело', CarbonImmutable::now()->addDays(3)));
    $foreign = SettlementMethod::factory()->create();
    $sushi = SettlementMethod::query()->where('code', 'sushi')->sole();
    $money = SettlementMethod::query()->where('code', 'money')->sole();
    foreach ([$foreign, $sushi, $money] as $method) {
        expect(fn () => app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(response: RequestResponse::Conditional, expectedVersion: 0, conditionType: ConditionType::Custom, description: 'Детали', dueAt: CarbonImmutable::now()->addDays(3), methodId: $method->id)))->toThrow(ValidationException::class);
    }
    expect($request->conditions()->count())->toBe(0);
});
it('provides valid catalog and condition factories', function (): void {
    expect(ExpenseCategory::factory()->create()->id)->toBeGreaterThan(0);
    expect(RequestTemplate::factory()->create()->category->id)->toBeGreaterThan(0);
    $condition = RequestCondition::factory()->create();
    expect($condition->proposed_by_id)->toBe($condition->partnerRequest->recipient_id);
});
