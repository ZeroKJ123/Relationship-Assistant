<?php

declare(strict_types=1);

use App\Actions\Couples\EndCouple;
use App\Actions\Dishes\SaveDish;
use App\Actions\Duties\ReportDuty;
use App\Actions\Duties\VerifyDuty;
use App\Actions\Requests\CreatePartnerRequest;
use App\Actions\Requests\RespondToRequest;
use App\Data\Dishes\DishData;
use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Enums\Currency;
use App\Enums\DishKind;
use App\Enums\HeartOperationType;
use App\Enums\RequestResponse;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\ReservationStatus;
use App\Models\Dish;
use App\Models\HeartReservation;
use App\Models\User;
use App\Services\Hearts\Ledger;
use App\Services\Media\PrivatePhotos;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
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
it('saves an exact estimate and private reencoded photo without duplicate files on retry', function (): void {
    $user = User::factory()->create();
    $photo = UploadedFile::fake()->image('private.png', 1600, 800);
    $data = new DishData('Омлет', 'Яйца', '12.30', Currency::Byn, DishKind::Ordinary, 2, 0, Str::uuid()->toString(), photo: $photo);
    $dish = app(SaveDish::class)->execute($user, $data);
    expect($dish->estimate)->toBe('12.30');
    expect($dish->photo_path)->toEndWith('.jpg');
    $stored = Storage::disk(PrivatePhotos::DISK)->get($dish->photo_path);
    $image = getimagesizefromstring($stored);
    expect($image[0])->toBe(1200);
    expect($image['mime'])->toBe('image/jpeg');
    expect(app(SaveDish::class)->execute($user, $data)->id)->toBe($dish->id);
    expect(Storage::disk(PrivatePhotos::DISK)->allFiles())->toHaveCount(1);
    $this->asUser($user)->get(route('photos.dishes', $dish))->assertOk()->assertHeader('content-type', 'image/jpeg');
    $this->get(route('dishes.index'))->assertOk()->assertSee('Омлет')->assertSee('12.30');
});
it('rejects wrong dish prices portions and fractional precision', function (DishKind $kind, int $price, int $portions, string $estimate): void {
    $user = User::factory()->create();
    expect(fn () => app(SaveDish::class)->execute($user, new DishData('Торт', 'Мука', $estimate, Currency::Byn, $kind, $portions, $price, Str::uuid()->toString())))->toThrow(ValidationException::class);
    expect(Dish::query()->count())->toBe(0);
})->with([[DishKind::Ordinary, 5, 1, '1.00'], [DishKind::Special, 1, 1, '1.00'], [DishKind::Special, 51, 1, '1.00'], [DishKind::Ordinary, 0, 0, '1.00'], [DishKind::Ordinary, 0, 1, '0.001']]);
it('protects ownership stale edits photo privacy and past partners', function (): void {
    $pair = AgreementScenario::create();
    $dish = Dish::factory()->create(['user_id' => $pair->first->id, 'photo_path' => 'original.jpg']);
    Storage::disk(PrivatePhotos::DISK)->put('original.jpg', 'image');
    $this->asUser($pair->second)->get(route('photos.dishes', $dish))->assertOk();
    $this->get(route('dishes.edit', $dish))->assertForbidden();
    $this->asUser(User::factory()->create(['is_admin' => true]))->get(route('photos.dishes', $dish))->assertForbidden();
    $this->get(route('dishes.index', ['owner_id' => $pair->first->id]))->assertForbidden();
    $data = new DishData('Изменено', 'Текст', '5.00', Currency::Rub, DishKind::Ordinary, 1, 0, Str::uuid()->toString(), expectedVersion: 1, photo: UploadedFile::fake()->image('new.png'));
    expect(fn () => app(SaveDish::class)->execute($pair->first, $data, $dish))->toThrow(ValidationException::class);
    expect(Storage::disk(PrivatePhotos::DISK)->allFiles())->toBe(['original.jpg']);
    expect(fn () => app(SaveDish::class)->execute($pair->second, $data, $dish))->toThrow(AuthorizationException::class);
    app(EndCouple::class)->execute($pair->first, $pair->couple);
    $this->asUser($pair->second)->get(route('photos.dishes', $dish))->assertForbidden();
});
it('freezes the dish offer and reserves and settles the chosen heart price once', function (): void {
    $pair = AgreementScenario::create();
    $dish = Dish::factory()->create(['user_id' => $pair->second->id, 'title' => 'Пирог', 'kind' => DishKind::Special, 'heart_price' => 10]);
    $data = new NewRequestData(type: RequestType::Food, description: 'Пирог', dueAt: CarbonImmutable::now()->addDays(3), operationKey: Str::uuid()->toString(), heartPrice: 10, dishId: $dish->id, dishVersion: 0);
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, $data);
    app(SaveDish::class)->execute($pair->second, new DishData('Новый пирог', 'Другая мука', '99.99', Currency::Usd, DishKind::Special, 3, 20, Str::uuid()->toString()), $dish);
    expect(app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, $data)->id)->toBe($request->id);
    expect($request->dish_snapshot['title'])->toBe('Пирог');
    expect($request->heart_price)->toBe(10);
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Agree, 0));
    expect(HeartReservation::query()->sole()->amount)->toBe(10);
    $duty = $request->duties()->sole();
    app(ReportDuty::class)->execute($pair->second, $pair->couple, $duty, 0, true);
    app(VerifyDuty::class)->execute($pair->first, $pair->couple, $duty, 1, true);
    expect(HeartReservation::query()->sole()->status)->toBe(ReservationStatus::Settled);
    expect(app(Ledger::class)->getWallet($pair->first)->balance)->toBe(10);
});
it('denies free special dishes at negative balance on creation and acceptance but permits ordinary food', function (): void {
    $pair = AgreementScenario::create();
    $special = Dish::factory()->create(['user_id' => $pair->second->id, 'kind' => DishKind::Special]);
    $data = new NewRequestData(type: RequestType::Food, description: 'Особое', dueAt: CarbonImmutable::now()->addDays(3), dishId: $special->id, dishVersion: 0);
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, $data);
    app(Ledger::class)->apply($pair->first, -30, HeartOperationType::FalseReportPenalty, 'test:negative');
    expect(fn () => app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Agree, 0)))->toThrow(ValidationException::class);
    expect($request->refresh()->status)->toBe(RequestStatus::Pending);
    expect(fn () => app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, $data))->toThrow(ValidationException::class);
    $ordinary = Dish::factory()->create(['user_id' => $pair->second->id]);
    $normal = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(type: RequestType::Food, description: 'Обычное', dueAt: $data->dueAt, dishId: $ordinary->id, dishVersion: 0));
    expect($normal->heart_price)->toBe(0);
});
it('rejects changed and foreign catalog sources and unsupported photo formats', function (): void {
    $pair = AgreementScenario::create();
    $dish = Dish::factory()->create(['user_id' => $pair->second->id, 'version' => 1]);
    expect(fn () => app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(type: RequestType::Food, description: 'Старое', dueAt: CarbonImmutable::tomorrow(), dishId: $dish->id, dishVersion: 0)))->toThrow(ValidationException::class);
    $foreign = Dish::factory()->create();
    expect(fn () => app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(type: RequestType::Food, description: 'Чужое', dueAt: CarbonImmutable::tomorrow(), dishId: $foreign->id, dishVersion: 0)))->toThrow(ValidationException::class);
    $this->asUser($pair->first)->post(route('dishes.store'), ['title' => 'Фото', 'ingredients' => 'Мука', 'estimate' => '0', 'currency' => 'BYN', 'kind' => 'ordinary', 'portions' => 1, 'heart_price' => 0, 'expected_version' => 0, 'operation_key' => Str::uuid()->toString(), 'photo' => UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml')])->assertSessionHasErrors('photo');
    $this->get(route('dishes.create'))->assertOk();
    $this->get(route('requests.create', [$pair->couple, 'dish_id' => $dish->id]))->assertOk()->assertSee('dish_version');
});

it('keeps the original creation key after editing and never duplicates an old creation retry', function (): void {
    $user = User::factory()->create();
    $creation = new DishData('Первое', 'Яйца', '3.20', Currency::Byn, DishKind::Ordinary, 1, 0, Str::uuid()->toString());
    $dish = app(SaveDish::class)->execute($user, $creation);
    $change = new DishData('Второе', 'Яйца и сыр', '5.20', Currency::Byn, DishKind::Ordinary, 1, 0, Str::uuid()->toString());
    app(SaveDish::class)->execute($user, $change, $dish);
    expect(app(SaveDish::class)->execute($user, $change, $dish)->version)->toBe(1);
    expect($dish->refresh()->operation_key)->toBe($creation->operationKey);
    expect(fn () => app(SaveDish::class)->execute($user, $creation))->toThrow(ValidationException::class);
    expect(Dish::query()->count())->toBe(1);
});

it('creates and updates dishes through validated forms using the currency of the pair', function (): void {
    $pair = AgreementScenario::create();
    $pair->couple->forceFill(['currency' => Currency::Eur])->save();
    $this->asUser($pair->first)->get(route('dishes.create'))->assertOk()->assertSee('value="EUR" selected', false);
    $payload = ['title' => 'Суп', 'ingredients' => 'Овощи', 'estimate' => '10.30', 'currency' => 'EUR', 'kind' => 'ordinary', 'portions' => 2, 'heart_price' => 0, 'expected_version' => 0, 'operation_key' => Str::uuid()->toString()];
    $this->post(route('dishes.store'), $payload)->assertRedirect(route('dishes.index'));
    $dish = Dish::query()->sole();
    $this->put(route('dishes.update', $dish), [...$payload, 'title' => 'Другой суп', 'operation_key' => Str::uuid()->toString()])->assertRedirect();
    expect($dish->refresh()->title)->toBe('Другой суп');
});
