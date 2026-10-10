<?php

declare(strict_types=1);

use App\Actions\Couples\EndCouple;
use App\Actions\Duties\ReportDuty;
use App\Actions\Duties\VerifyDuty;
use App\Actions\Requests\CancelPartnerRequest;
use App\Actions\Requests\CreatePartnerRequest;
use App\Actions\Requests\ResolveCondition;
use App\Actions\Requests\RespondToRequest;
use App\Actions\Wishlist\ArchiveWishlistItem;
use App\Actions\Wishlist\SaveWishlistItem;
use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Data\Wishlist\WishlistItemData;
use App\Enums\ConditionType;
use App\Enums\Currency;
use App\Enums\DutyKind;
use App\Enums\DutyStatus;
use App\Enums\RequestResponse;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Models\WishlistItem;
use App\Services\Hearts\Ledger;
use App\Services\Media\PrivatePhotos;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
    Storage::fake(PrivatePhotos::DISK);
});
function giftCondition(AgreementScenario $pair, WishlistItem $wish): PartnerRequest
{
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(type: RequestType::Service, description: 'Помочь', dueAt: CarbonImmutable::now()->addDays(3)));
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(response: RequestResponse::Conditional, expectedVersion: 0, conditionType: ConditionType::Gift, description: 'Подарить желание', dueAt: CarbonImmutable::now()->addDays(3), wishlistItemId: $wish->id, wishlistVersion: $wish->version));

    return $request;
}
it('saves and edits exact wishlist prices with private photos and version checks', function (): void {
    $pair = AgreementScenario::create();
    $data = new WishlistItemData('Книга', 'Интересная', '19.95', Currency::Eur, Str::uuid()->toString(), photo: UploadedFile::fake()->image('book.png'));
    $wish = app(SaveWishlistItem::class)->execute($pair->second, $data);
    expect(app(SaveWishlistItem::class)->execute($pair->second, $data)->id)->toBe($wish->id);
    expect($wish->price)->toBe('19.95');
    $this->asUser($pair->first)->get(route('wishlist.index', ['owner_id' => $pair->second->id]))->assertOk()->assertSee('19.95');
    $this->get(route('photos.wishlist', $wish))->assertOk();
    $this->get(route('wishlist.edit', $wish))->assertForbidden();
    $this->asUser(User::factory()->create(['is_admin' => true]))->get(route('photos.wishlist', $wish))->assertForbidden();
    $this->asUser($pair->second)->get(route('wishlist.edit', $wish))->assertOk();
    expect(fn () => app(SaveWishlistItem::class)->execute($pair->second, new WishlistItemData('Книга', null, '19.951', Currency::Eur, Str::uuid()->toString()), $wish))->toThrow(ValidationException::class);
});
it('claims one wish and archives it only after its receiver confirms the gift without a reward', function (): void {
    $pair = AgreementScenario::create();
    $wish = WishlistItem::factory()->create(['user_id' => $pair->second->id, 'title' => 'Книга']);
    $request = giftCondition($pair, $wish);
    app(ResolveCondition::class)->execute($pair->first, $pair->couple, $request, 1);
    app(ResolveCondition::class)->execute($pair->first, $pair->couple, $request, 1);
    $condition = $request->conditions()->sole();
    expect($wish->refresh()->claimed_condition_id)->toBe($condition->id);
    app(SaveWishlistItem::class)->execute($pair->second, new WishlistItemData('Новое название', null, '88.00', Currency::Usd, Str::uuid()->toString(), expectedVersion: $wish->version), $wish);
    expect($condition->gift_snapshot['title'])->toBe('Книга');
    expect($condition->gift_snapshot['price'])->toBe('49.99');
    $duty = $request->duties()->where('kind', DutyKind::Condition)->sole();
    expect($duty->is_reward_eligible)->toBeFalse();
    app(ReportDuty::class)->execute($pair->first, $pair->couple, $duty, 0, true);
    expect($wish->refresh()->received_at)->toBeNull();
    expect($duty->refresh()->status)->toBe(DutyStatus::Reported);
    app(VerifyDuty::class)->execute($pair->second, $pair->couple, $duty, 1, true);
    app(VerifyDuty::class)->execute($pair->second, $pair->couple, $duty, 1, true);
    expect($wish->refresh()->received_at)->not->toBeNull();
    expect($wish->archived_at)->not->toBeNull();
    expect($wish->claimed_condition_id)->toBeNull();
    expect(app(Ledger::class)->getWallet($pair->first)->balance)->toBe(20);
    expect(fn () => giftCondition($pair, $wish))->toThrow(ValidationException::class);
    $this->asUser($pair->second)->get(route('wishlist.index', ['archive' => 1]))->assertOk()->assertSee('Подарок получен');
});
it('does not accept a changed archived or already promised wish and preserves rollback', function (): void {
    $pair = AgreementScenario::create();
    $wish = WishlistItem::factory()->create(['user_id' => $pair->second->id]);
    $one = giftCondition($pair, $wish);
    $two = giftCondition($pair, $wish);
    app(ResolveCondition::class)->execute($pair->first, $pair->couple, $one, 1);
    expect(fn () => app(ResolveCondition::class)->execute($pair->first, $pair->couple, $two, 1))->toThrow(ValidationException::class);
    expect($two->refresh()->status)->toBe(RequestStatus::Conditional);
    expect($two->conditions()->sole()->accepted_at)->toBeNull();
    expect(fn () => app(ArchiveWishlistItem::class)->execute($pair->second, $wish, $wish->refresh()->version))->toThrow(ValidationException::class);
    $other = WishlistItem::factory()->create(['user_id' => $pair->second->id]);
    $request = giftCondition($pair, $other);
    app(ArchiveWishlistItem::class)->execute($pair->second, $other, 0);
    expect(fn () => app(ResolveCondition::class)->execute($pair->first, $pair->couple, $request, 1))->toThrow(ValidationException::class);
    $edited = WishlistItem::factory()->create(['user_id' => $pair->second->id]);
    $request = giftCondition($pair, $edited);
    app(SaveWishlistItem::class)->execute($pair->second, new WishlistItemData('Иное', null, '5.00', Currency::Rub, Str::uuid()->toString()), $edited);
    expect(fn () => app(ResolveCondition::class)->execute($pair->first, $pair->couple, $request, 1))->toThrow(ValidationException::class);
});
it('releases unreceived gift promises on mutual cancellation dispute or pair exit', function (string $ending): void {
    $pair = AgreementScenario::create();
    $wish = WishlistItem::factory()->create(['user_id' => $pair->second->id]);
    $request = giftCondition($pair, $wish);
    app(ResolveCondition::class)->execute($pair->first, $pair->couple, $request, 1);
    if ($ending === 'cancel') {
        app(CancelPartnerRequest::class)->execute($pair->first, $pair->couple, $request, 2);
        app(CancelPartnerRequest::class)->execute($pair->second, $pair->couple, $request, 3);
    }
    if ($ending === 'exit') {
        app(EndCouple::class)->execute($pair->second, $pair->couple);
    }
    if ($ending === 'dispute') {
        $duty = $request->duties()->where('kind', DutyKind::Condition)->sole();
        app(ReportDuty::class)->execute($pair->first, $pair->couple, $duty, 0, true);
        app(VerifyDuty::class)->execute($pair->second, $pair->couple, $duty, 1, false);
    }
    expect($wish->refresh()->claimed_condition_id)->toBeNull();
    expect($wish->received_at)->toBeNull();
})->with(['cancel', 'exit', 'dispute']);
it('rejects another persons gift and keeps snapshot photo access after private catalog sharing ends', function (): void {
    $pair = AgreementScenario::create();
    $foreign = WishlistItem::factory()->create();
    expect(fn () => giftCondition($pair, $foreign))->toThrow(ValidationException::class);
    $wish = app(SaveWishlistItem::class)->execute($pair->second, new WishlistItemData('Подарок', null, '10', Currency::Byn, Str::uuid()->toString(), photo: UploadedFile::fake()->image('gift.png')));
    $request = giftCondition($pair, $wish);
    $condition = $request->conditions()->sole();
    app(EndCouple::class)->execute($pair->second, $pair->couple);
    $this->asUser($pair->first)->get(route('photos.wishlist', $wish))->assertForbidden();
    $this->get(route('photos.conditions', $condition))->assertOk();
    $this->asUser(User::factory()->create())->get(route('photos.conditions', $condition))->assertForbidden();
});

it('keeps wishlist creation retries safe after editing and requires archive confirmation', function (): void {
    $pair = AgreementScenario::create();
    $data = new WishlistItemData('Первое', null, '10.30', Currency::Byn, Str::uuid()->toString());
    $wish = app(SaveWishlistItem::class)->execute($pair->first, $data);
    app(SaveWishlistItem::class)->execute($pair->first, new WishlistItemData('Второе', null, '12.30', Currency::Byn, Str::uuid()->toString()), $wish);
    expect(fn () => app(SaveWishlistItem::class)->execute($pair->first, $data))->toThrow(ValidationException::class);
    expect(WishlistItem::query()->count())->toBe(1);
    $this->asUser($pair->first)->post(route('wishlist.archive', $wish), ['expected_version' => 1])->assertSessionHasErrors('confirmation');
    $this->post(route('wishlist.archive', $wish), ['expected_version' => 1, 'confirmation' => 1])->assertRedirect();
    expect($wish->refresh()->archived_at)->not->toBeNull();
});
