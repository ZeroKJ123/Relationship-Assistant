<?php

declare(strict_types=1);

use App\Actions\Requests\CreatePartnerRequest;
use App\Actions\Requests\RespondToRequest;
use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Enums\Currency;
use App\Enums\DishKind;
use App\Enums\HeartOperationType;
use App\Enums\RequestResponse;
use App\Enums\RequestType;
use App\Models\Dish;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Services\Hearts\Ledger;
use App\ViewModels\DashboardViewModel;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
});

it('keeps ordinary food available at a negative balance but disables special food including free special food', function (): void {
    $pair = AgreementScenario::create();
    app(Ledger::class)->apply($pair->first, -25, HeartOperationType::ManualDeduction, 'screens:negative');
    $ordinary = Dish::factory()->create(['user_id' => $pair->second->id, 'kind' => DishKind::Ordinary, 'heart_price' => 0, 'estimate' => '8.20', 'currency' => Currency::Rub]);
    $special = Dish::factory()->create(['user_id' => $pair->second->id, 'kind' => DishKind::Special, 'heart_price' => 0]);
    $this->asUser($pair->first)->get(route('dishes.index', ['owner_id' => $pair->second->id]))->assertOk()->assertSee('8.20 RUB')->assertSee('Заказ: бесплатно')->assertSee(route('requests.create', [$pair->couple, 'dish_id' => $ordinary->id]), false)->assertDontSee(route('requests.create', [$pair->couple, 'dish_id' => $special->id]), false)->assertSee('Особое блюдо недоступно');
});

it('shows insufficient free hearts and filters without mixing up product estimates and heart prices', function (): void {
    $pair = AgreementScenario::create();
    $special = Dish::factory()->create(['user_id' => $pair->second->id, 'kind' => DishKind::Special, 'heart_price' => 30, 'estimate' => '99.00']);
    $ordinary = Dish::factory()->create(['user_id' => $pair->second->id, 'title' => 'Обычный суп', 'kind' => DishKind::Ordinary, 'heart_price' => 0]);
    $this->asUser($pair->first)->get(route('dishes.index', ['owner_id' => $pair->second->id, 'kind' => 'special']))->assertOk()->assertSee($special->title)->assertDontSee($ordinary->title)->assertSee('99.00')->assertSee('Заказ: ♥ 30')->assertSee('Не хватает свободных сердечек');
});

it('offers a progressive condition sheet and the food money exception without changing shared responses', function (): void {
    $pair = AgreementScenario::create();
    $this->seed(CatalogSeeder::class);
    $request = PartnerRequest::factory()->create(['couple_id' => $pair->couple->id, 'author_id' => $pair->first->id, 'recipient_id' => $pair->second->id, 'type' => RequestType::Food]);
    $this->asUser($pair->second)->get(route('requests.show', [$pair->couple, $request]))->assertOk()->assertSee('data-condition-form', false)->assertSee('Нет денег на продукты — без списания')->assertSee('data-condition-choice="sushi"', false)->assertSee('Маленький')->assertSee('Средний')->assertSee('Большой')->assertSee('Автор просьбы примет этот вариант отдельно.');
    $this->asUser($pair->first)->get(route('requests.show', [$pair->couple, $request]))->assertOk()->assertDontSee('data-condition-form', false);
});

it('counts accepted reservations when deciding which kitchen offers are available', function (): void {
    $pair = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Service, 'Услуга с резервом', CarbonImmutable::now()->addDays(1), operationKey: Str::uuid()->toString(), heartPrice: 15));
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Agree, 0));
    Dish::factory()->create(['user_id' => $pair->second->id, 'kind' => DishKind::Special, 'heart_price' => 10]);
    $this->asUser($pair->first)->get(route('dishes.index', ['owner_id' => $pair->second->id]))->assertOk()->assertSee('Свободно: 5 · резерв: 15')->assertSee('Не хватает свободных сердечек');
    $this->get('/')->assertOk()->assertViewHas('dashboard', fn (DashboardViewModel $dashboard): bool => $dashboard->wallet->balance === 20 && $dashboard->wallet->reserved === 15 && $dashboard->wallet->available === 5);
});

it('uses the personal currency before joining a couple and the shared currency afterwards', function (): void {
    $user = User::factory()->create(['currency' => Currency::Usd]);
    $this->asUser($user)->get(route('dishes.create'))->assertOk()->assertViewHas('defaultCurrency', Currency::Usd);
    $pair = AgreementScenario::create();
    $pair->first->forceFill(['currency' => Currency::Usd])->save();
    $pair->couple->forceFill(['currency' => Currency::Eur])->save();
    $this->asUser($pair->first)->get(route('dishes.create'))->assertOk()->assertViewHas('defaultCurrency', Currency::Eur);
});

it('keeps condition errors visible and opens the sheet only for a rejected condition', function (string $response, string $isOpen): void {
    $pair = AgreementScenario::create();
    $request = PartnerRequest::factory()->create(['couple_id' => $pair->couple->id, 'author_id' => $pair->first->id, 'recipient_id' => $pair->second->id]);
    $errors = (new ViewErrorBag)->put('default', new MessageBag(['due_at' => 'Срок уже прошёл. Обновите своё решение.']));
    $this->asUser($pair->second)->withSession(['errors' => $errors, '_old_input' => ['response' => $response]]);
    session()->save();
    $page = $this->get(route('requests.show', [$pair->couple, $request]));
    $page->assertOk()->assertSee(sprintf('data-condition-errors="%s"', $isOpen), false)->assertSee('Срок уже прошёл. Обновите своё решение.');
})->with([['conditional', 'true'], ['agree', 'false']]);
