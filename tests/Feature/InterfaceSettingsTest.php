<?php

declare(strict_types=1);

use App\Models\Car;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('shows cars on the home screen only when the current user has added a car', function (): void {
    $pair = AgreementScenario::create();
    Car::factory()->create(['user_id' => $pair->second->id]);
    $this->asUser($pair->first)->get('/')->assertOk()->assertDontSee('Мои машины')->assertDontSee('Мои пары');
    $this->get(route('profile.show'))->assertOk()->assertSee('Добавить машину')->assertSee('Подробные настройки')->assertDontSee('Мои пары');
    Car::factory()->create(['user_id' => $pair->first->id]);
    $this->get('/')->assertOk()->assertSee('Мои машины');
});

it('puts all request types in the topic selector without templates and extra footer actions', function (): void {
    $pair = AgreementScenario::create();
    $this->asUser($pair->first)->get(route('requests.create', $pair->couple))->assertOk()->assertSee('О чём просьба')->assertSee('Денежная просьба')->assertSee('Гости')->assertSee('Совместное событие')->assertDontSee('Выбрать шаблон')->assertDontSee('Другие просьбы')->assertDontSee('Все просьбы')->assertDontSee('Время пары');
    $this->get(route('requests.index', $pair->couple))->assertOk()->assertDontSee('Попросить деньги')->assertDontSee(sprintf('href="%s"', route('couples.show', $pair->couple)), false);
});

it('sends a mood question with a brief response and no persistent page status', function (): void {
    $pair = AgreementScenario::create();
    $this->asUser($pair->first)->postJson(route('mood.prompts.store', $pair->couple))->assertOk()->assertExactJson(['message' => 'Запрос отправлен']);
    $this->get('/')->assertOk()->assertSee('data-mood-request', false)->assertDontSee('Запрос настроения создан');
});

it('updates timezone from advanced profile settings with ownership confirmation and stale checks', function (): void {
    $pair = AgreementScenario::create();
    $data = ['timezone' => 'Europe/London', 'expected_timezone' => $pair->couple->timezone, 'confirmation' => '1'];
    $this->asUser($pair->first)->patch(route('profile.timezone', $pair->couple), $data)->assertRedirect(route('profile.show'));
    expect($pair->couple->refresh()->timezone)->toBe('Europe/London');
    $this->patch(route('profile.timezone', $pair->couple), $data)->assertSessionHasErrors('timezone');
    $this->patch(route('profile.timezone', $pair->couple), ['timezone' => 'bad', 'expected_timezone' => 'Europe/London'])->assertSessionHasErrors(['timezone', 'confirmation']);
    $this->asUser(User::factory()->create())->patch(route('profile.timezone', $pair->couple), $data)->assertForbidden();
});
