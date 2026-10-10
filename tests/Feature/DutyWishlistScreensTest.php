<?php

declare(strict_types=1);

use App\Actions\Requests\ResolveCondition;
use App\Actions\Requests\RespondToRequest;
use App\Data\Requests\RequestResponseData;
use App\Enums\ConditionType;
use App\Enums\DutyStatus;
use App\Enums\RequestResponse;
use App\Models\Duty;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Models\WishlistItem;
use App\Services\Hearts\Ledger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DutyScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
});

it('groups duty statuses without exposing another couple', function (DutyStatus $status, string $filter): void {
    $scenario = DutyScenario::create();
    $scenario->duty->forceFill(['status' => $status, 'description' => 'Наше дело в выбранной вкладке'])->save();
    $other = DutyScenario::create();
    $other->duty->forceFill(['status' => $status, 'description' => 'Чужая приватная обязанность'])->save();
    $page = $this->asUser($scenario->pair->second)->get(route('duties.index', [$scenario->pair->couple, 'filter' => $filter]))->assertOk()->assertSee('Наше дело в выбранной вкладке')->assertDontSee('Чужая приватная обязанность')->assertSee('На проверке');
    if ($filter === 'completed') {
        $page->assertDontSee('После подтверждения: до +');
    }
    if ($filter !== 'completed') {
        $page->assertSee('После подтверждения: до +');
    }
    $this->get(route('duties.index', [$other->pair->couple]))->assertForbidden();
})->with([
    [DutyStatus::Pending, 'active'], [DutyStatus::Overdue, 'active'], [DutyStatus::Reported, 'waiting'],
    [DutyStatus::Confirmed, 'completed'], [DutyStatus::Disputed, 'completed'], [DutyStatus::Cancelled, 'completed'],
]);

it('keeps filtered duties bounded and separates waiting from active tasks', function (): void {
    $scenario = DutyScenario::create();
    foreach (range(1, 10) as $number) {
        $source = PartnerRequest::factory()->create(['couple_id' => $scenario->pair->couple->id, 'author_id' => $scenario->pair->first->id, 'recipient_id' => $scenario->pair->second->id]);
        Duty::factory()->create(['partner_request_id' => $source->id, 'couple_id' => $scenario->pair->couple->id, 'executor_id' => $scenario->pair->second->id, 'reviewer_id' => $scenario->pair->first->id]);
    }
    $this->asUser($scenario->pair->second)->get(route('duties.index', [$scenario->pair->couple, 'filter' => 'active']))->assertOk()->assertViewHas('duties', fn ($duties): bool => $duties->count() === 10 && $duties->total() === 11)->assertSee('filter=active', false);
    $this->get(route('duties.index', [$scenario->pair->couple, 'filter' => 'waiting']))->assertOk()->assertSee('В выбранной вкладке нет дел.');
});

it('requires two distinct confirmed stages and never rewards the executor for the first mark alone', function (): void {
    $scenario = DutyScenario::create();
    $couple = $scenario->pair->couple;
    $duty = $scenario->duty;
    $this->asUser($scenario->pair->second)->get(route('duties.show', [$couple, $duty]))->assertOk()->assertSee('data-duty-confirmation', false)->assertSee('Отметка сама по себе не начисляет сердечки.')->assertDontSee('Проверка партнёром');
    $this->post(route('duties.report', [$couple, $duty]), ['answer' => 1, 'expected_version' => 0])->assertSessionHasErrors('confirmation');
    $this->post(route('duties.report', [$couple, $duty]), ['answer' => 1, 'expected_version' => 0, 'confirmation' => 1])->assertRedirect();
    expect($duty->refresh()->status)->toBe(DutyStatus::Reported);
    expect(app(Ledger::class)->getWallet($scenario->pair->second)->balance)->toBe(20);
    $this->get(route('duties.show', [$couple, $duty]))->assertOk()->assertSee('Своевременная отметка не штрафуется')->assertDontSee('data-duty-confirmation', false);
    $this->asUser($scenario->pair->first)->get(route('duties.show', [$couple, $duty]))->assertOk()->assertSee('Проверка партнёром')->assertSee('data-duty-confirmation', false);
    $this->post(route('duties.verify', [$couple, $duty]), ['answer' => 1, 'expected_version' => 1, 'confirmation' => 1])->assertRedirect();
    expect($duty->refresh()->status)->toBe(DutyStatus::Confirmed);
    expect(app(Ledger::class)->getWallet($scenario->pair->second)->balance)->toBeGreaterThan(20);
});

it('shows wishlist prices and available gift guidance while keeping owner actions private', function (): void {
    $scenario = DutyScenario::create();
    $wish = WishlistItem::factory()->create(['user_id' => $scenario->pair->second->id, 'title' => str_repeat('Длинное желание ', 7), 'description' => '<script>Личный текст</script>', 'price' => '78.35']);
    $this->asUser($scenario->pair->second)->get(route('wishlist.index'))->assertOk()->assertSee('78.35 BYN')->assertSee('Можно выбрать как условие входящей просьбы.')->assertSee(route('wishlist.edit', $wish), false)->assertSee('<script>Личный текст</script>')->assertDontSee('<script>Личный текст</script>', false);
    $this->asUser($scenario->pair->first)->get(route('wishlist.index', ['owner_id' => $scenario->pair->second->id]))->assertOk()->assertSee('Партнёр может выбрать это желание')->assertDontSee(route('wishlist.edit', $wish), false)->assertDontSee('Подтверждаю перенос в архив');
    $this->asUser(User::factory()->create())->get(route('wishlist.index', ['owner_id' => $scenario->pair->second->id]))->assertForbidden();
});

it('distinguishes promised and received wishlist gifts from available wishes', function (): void {
    $scenario = DutyScenario::create();
    $wish = WishlistItem::factory()->create(['user_id' => $scenario->pair->second->id]);
    $request = PartnerRequest::factory()->create(['couple_id' => $scenario->pair->couple->id, 'author_id' => $scenario->pair->first->id, 'recipient_id' => $scenario->pair->second->id]);
    app(RespondToRequest::class)->execute($scenario->pair->second, $scenario->pair->couple, $request, new RequestResponseData(response: RequestResponse::Conditional, expectedVersion: 0, conditionType: ConditionType::Gift, description: 'Подарок', dueAt: CarbonImmutable::now()->addDays(3), wishlistItemId: $wish->id, wishlistVersion: $wish->version));
    app(ResolveCondition::class)->execute($scenario->pair->first, $scenario->pair->couple, $request, 1);
    $this->asUser($scenario->pair->second)->get(route('wishlist.index'))->assertOk()->assertSee('Подарок обещан')->assertSee('перенос в архив пока недоступен.')->assertDontSee('Подтверждаю перенос в архив');
    WishlistItem::factory()->create(['user_id' => $scenario->pair->second->id, 'title' => 'Полученное желание', 'archived_at' => now(), 'received_at' => now()]);
    $this->asUser($scenario->pair->second)->get(route('wishlist.index', ['archive' => 1]))->assertOk()->assertSee('Полученное желание')->assertSee('Подарок получен')->assertDontSee('Подтверждаю перенос в архив');
    $this->get(route('wishlist.create'))->assertOk()->assertSee('data-wishlist-editor', false)->assertSee('Покупку и передачу подарка вы организуете самостоятельно.');
});
