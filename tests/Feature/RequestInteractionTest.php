<?php

declare(strict_types=1);

use App\Actions\Mood\RecordMood;
use App\Actions\Mood\RequestPartnerMood;
use App\Actions\Requests\CreatePartnerRequest;
use App\Data\Mood\MoodData;
use App\Data\Requests\NewRequestData;
use App\Enums\MoodLevel;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\PartnerRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('defaults ordinary requests to a service and keeps the optional settings folded', function (): void {
    $pair = AgreementScenario::create();
    $response = $this->asUser($pair->first)->get(route('requests.create', $pair->couple))->assertOk();
    $response->assertViewHas('selectedType', 'service')->assertViewHas('defaultDueAt', CarbonImmutable::now($pair->couple->timezone)->addDay()->format('Y-m-d\TH:i'));
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//select[@name="type"]/option[@selected]')->item(0)?->getAttribute('value'))->toBe('service');
    expect($xpath->query('//input[@name="heart_price"]/ancestor::details[not(@open)]')->length)->toBe(1);
    expect($xpath->query('//select[@name="type"]/option[@value="guests" or @value="event"]')->length)->toBe(2);
    $this->get(route('requests.create', [$pair->couple, 'type' => 'rating']))->assertViewHas('selectedType', 'rating');
});

it('exposes a condition without javascript and independently accepts it as the request author', function (): void {
    $pair = AgreementScenario::create();
    $this->asUser($pair->first)->post(route('requests.store', $pair->couple), ['operation_key' => Str::uuid()->toString(), 'type' => 'service', 'description' => 'Помоги с ужином', 'due_at' => CarbonImmutable::now($pair->couple->timezone)->addDay()->format('Y-m-d\TH:i')])->assertRedirect();
    $request = PartnerRequest::query()->sole();
    $page = $this->asUser($pair->second)->get(route('requests.show', [$pair->couple, $request]))->assertOk()->assertSee('Согласиться с условием')->assertDontSee('Цена: 0 сердечек');
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$page->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//details/summary[contains(.,"Согласиться с условием") and not(@hidden)]')->length)->toBe(1);
    $route = route('requests.responses.store', [$pair->couple, $request]);
    $payload = ['expected_version' => 0, 'response' => 'conditional', 'condition_type' => 'custom', 'description' => 'Ты помоешь посуду', 'due_at' => CarbonImmutable::now($pair->couple->timezone)->addDay()->format('Y-m-d\TH:i')];
    $this->post($route, $payload)->assertSessionHasErrors('confirmation');
    $this->post($route, [...$payload, 'confirmation' => 1])->assertRedirect();
    expect($request->refresh()->status)->toBe(RequestStatus::Conditional)->and($request->duties()->count())->toBe(0);
    $this->asUser($pair->second)->get(route('requests.show', [$pair->couple, $request]))->assertSee('Изменить условие')->assertDontSee('Принимаю условие');
    $this->asUser($pair->first)->get(route('requests.show', [$pair->couple, $request]))->assertSee('Ты помоешь посуду')->assertSee('Принимаю условие');
    $this->get(route('home'))->assertSee('Условие от')->assertSee('Помоги с ужином');
    $accept = route('requests.condition.store', [$pair->couple, $request]);
    $this->post($accept, ['expected_version' => 1, 'decision' => 'accept'])->assertSessionHasErrors('confirmation');
    $this->post($accept, ['expected_version' => 1, 'decision' => 'accept', 'confirmation' => 1])->assertRedirect();
    $this->post($accept, ['expected_version' => 1, 'decision' => 'accept', 'confirmation' => 1])->assertRedirect();
    expect($request->refresh()->status)->toBe(RequestStatus::Accepted)->and($request->conditions()->sole()->accepted_at)->not->toBeNull()->and($request->duties()->count())->toBe(2);
});

it('keeps the food refusal exception usable in its separate minimal answer form', function (): void {
    $pair = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Food, 'Свари суп', CarbonImmutable::now()->addDay()));
    $this->asUser($pair->second)->get(route('requests.show', [$pair->couple, $request]))->assertSee('Нет денег на продукты — без списания');
    $this->post(route('requests.responses.store', [$pair->couple, $request]), ['expected_version' => 0, 'response' => 'decline', 'reason' => 'no_food_money', 'confirmation' => 1])->assertRedirect();
    expect($request->refresh()->status)->toBe(RequestStatus::Declined)->and($request->decline_penalty)->toBe(0);
});

it('shows an expired request without response or condition controls', function (): void {
    $pair = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Service, 'Просроченная просьба', CarbonImmutable::now()->addHour()));
    $this->travel(2)->hours();
    $this->asUser($pair->second)->get(route('requests.show', [$pair->couple, $request]))->assertSee('Срок просьбы истёк')->assertDontSee('Согласиться с условием')->assertDontSee('Подтверждаю выбранный ответ');
});

it('puts partner mood requests on the home screen and removes an answered prompt', function (): void {
    $pair = AgreementScenario::create();
    $this->asUser($pair->first)->get(route('home'))->assertSee('Узнать настроение партнёра')->assertDontSee('Ответить о настроении');
    $prompt = app(RequestPartnerMood::class)->execute($pair->first, $pair->couple);
    $this->asUser($pair->second)->get(route('home'))->assertSee('Партнёр спрашивает, как вы себя чувствуете.')->assertSee('Ответить о настроении');
    app(RecordMood::class)->execute($pair->second, $pair->couple, new MoodData(MoodLevel::Good, Str::uuid()->toString(), promptId: $prompt->id));
    $this->get(route('home'))->assertDontSee('Ответить о настроении');
});

it('treats a cleared optional service price as free without accepting an invalid amount', function (): void {
    $pair = AgreementScenario::create();
    $payload = ['operation_key' => Str::uuid()->toString(), 'type' => 'service', 'description' => 'Помоги с уборкой', 'due_at' => CarbonImmutable::now($pair->couple->timezone)->addDay()->format('Y-m-d\TH:i'), 'heart_price' => ''];
    $this->asUser($pair->first)->post(route('requests.store', $pair->couple), $payload)->assertSessionHasNoErrors()->assertRedirect();
    expect(PartnerRequest::query()->sole()->heart_price)->toBe(0);
    $this->post(route('requests.store', $pair->couple), [...$payload, 'operation_key' => Str::uuid()->toString(), 'heart_price' => '-1'])->assertSessionHasErrors('heart_price');
    expect(PartnerRequest::query()->count())->toBe(1);
});
