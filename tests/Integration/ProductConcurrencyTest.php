<?php

declare(strict_types=1);
use App\Actions\Duties\ReportDuty;
use App\Actions\Money\CreateMoneyRequest;
use App\Actions\Requests\CreatePartnerRequest;
use App\Actions\Requests\ResolveCondition;
use App\Actions\Requests\RespondToRequest;
use App\Data\Money\MoneyRequestData;
use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Enums\ConditionType;
use App\Enums\Currency;
use App\Enums\DutyKind;
use App\Enums\DutyStatus;
use App\Enums\RequestResponse;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Dish;
use App\Models\MoneyRequest;
use App\Models\PartnerRequest;
use App\Models\WishlistItem;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\Support\AgreementScenario;
use Tests\Support\ConcurrentOperations;

uses(DatabaseMigrations::class);
it('accepts only one simultaneous promise for the same wishlist item', function (): void {
    $pair = AgreementScenario::create();
    $wish = WishlistItem::factory()->create(['user_id' => $pair->second->id]);
    $requests = [];
    foreach (['Первое', 'Второе'] as $description) {
        $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(type: RequestType::Service, description: $description, dueAt: CarbonImmutable::tomorrow()));
        app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(response: RequestResponse::Conditional, expectedVersion: 0, conditionType: ConditionType::Gift, description: 'Подарок', dueAt: CarbonImmutable::tomorrow(), wishlistItemId: $wish->id, wishlistVersion: 0));
        $requests[] = $request;
    }
    $results = (new ConcurrentOperations)->run('agreement-worker.php', array_map(fn ($request): array => [(string) $pair->first->id, (string) $pair->couple->id, 'request_accept_condition', (string) $request->id, '1'], $requests));
    sort($results);
    expect($results)->toBe(['accepted', 'rejected']);
    expect($wish->refresh()->claimed_condition_id)->not->toBeNull();
    expect(PartnerRequest::query()->where('couple_id', $pair->couple->id)->where('status', RequestStatus::Accepted)->count())->toBe(1);
});
it('serializes competing catalog edits without overwriting the winner', function (): void {
    $pair = AgreementScenario::create();
    $dish = Dish::factory()->create(['user_id' => $pair->second->id]);
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $pair->second->id, (string) $pair->couple->id, 'dish_edit', (string) $dish->id, '11'], [(string) $pair->second->id, (string) $pair->couple->id, 'dish_edit', (string) $dish->id, '12']]);
    sort($results);
    expect($results)->toBe(['accepted', 'rejected']);
    expect($dish->refresh()->version)->toBe(1);
    expect($dish->title)->toBeIn(['Edit 11', 'Edit 12']);
});
it('creates only one exact monetary request for simultaneous duplicate submissions', function (): void {
    $pair = AgreementScenario::create();
    $pair->first->forceFill(['payment_details' => 'TEST race details'])->save();
    $arguments = [(string) $pair->first->id, (string) $pair->couple->id, 'money_create', '101', '0'];
    expect((new ConcurrentOperations)->run('agreement-worker.php', [$arguments, $arguments]))->toBe(['accepted', 'accepted']);
    expect(MoneyRequest::query()->count())->toBe(1);
    expect(MoneyRequest::query()->sole()->amount)->toBe('12.30');
});
it('records money receipt once when the receiver repeats confirmation concurrently', function (): void {
    $pair = AgreementScenario::create();
    $pair->first->forceFill(['payment_details' => 'TEST details'])->save();
    $request = app(CreateMoneyRequest::class)->execute($pair->first, $pair->couple, new MoneyRequestData('12.30', Currency::Byn, null, CarbonImmutable::tomorrow(), Str::uuid()->toString()));
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Agree, 0));
    $duty = $request->duties()->sole();
    app(ReportDuty::class)->execute($pair->second, $pair->couple, $duty, 0, true);
    $arguments = [(string) $pair->first->id, (string) $pair->couple->id, 'duty_verify', (string) $duty->id, '1'];
    expect((new ConcurrentOperations)->run('agreement-worker.php', [$arguments, $arguments]))->toBe(['accepted', 'accepted']);
    expect($duty->refresh()->status)->toBe(DutyStatus::Confirmed);
    expect($duty->confirmations()->where('stage', 'verify')->count())->toBe(1);
});
it('archives a gift once when its receiver confirms concurrently', function (): void {
    $pair = AgreementScenario::create();
    $wish = WishlistItem::factory()->create(['user_id' => $pair->second->id]);
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(type: RequestType::Service, description: 'Помочь', dueAt: CarbonImmutable::tomorrow()));
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(response: RequestResponse::Conditional, expectedVersion: 0, conditionType: ConditionType::Gift, description: 'Подарок', dueAt: CarbonImmutable::tomorrow(), wishlistItemId: $wish->id, wishlistVersion: 0));
    app(ResolveCondition::class)->execute($pair->first, $pair->couple, $request, 1);
    $duty = $request->duties()->where('kind', DutyKind::Condition)->sole();
    app(ReportDuty::class)->execute($pair->first, $pair->couple, $duty, 0, true);
    $arguments = [(string) $pair->second->id, (string) $pair->couple->id, 'duty_verify', (string) $duty->id, '1'];
    expect((new ConcurrentOperations)->run('agreement-worker.php', [$arguments, $arguments]))->toBe(['accepted', 'accepted']);
    expect($wish->refresh()->version)->toBe(2);
    expect($wish->received_at)->not->toBeNull();
    expect($duty->confirmations()->where('stage', 'verify')->count())->toBe(1);
});
