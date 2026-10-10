<?php

declare(strict_types=1);

namespace App\Actions\Dishes;

use App\Data\Dishes\DishData;
use App\Enums\DishKind;
use App\Models\Dish;
use App\Models\User;
use App\Services\Catalogs\CatalogVersions;
use App\Services\Media\PrivatePhotos;
use App\Services\Money\DecimalAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

final class SaveDish
{
    public function __construct(private readonly DecimalAmount $amounts, private readonly PrivatePhotos $photos, private readonly CatalogVersions $versions) {}

    public function execute(User $actor, DishData $data, ?Dish $item = null): Dish
    {
        if ($item !== null) {
            Gate::forUser($actor)->authorize('update', $item);
        }
        if ($item === null && $data->expectedVersion !== 0) {
            throw ValidationException::withMessages(['expected_version' => __('Новая карточка должна начинаться с исходной версии.')]);
        }
        $amount = $this->amounts->normalize($data->estimate, 'estimate');
        $payload = ['title' => $data->title, 'ingredients' => $data->ingredients, 'estimate' => $amount, 'currency' => $data->currency, 'kind' => $data->kind, 'portions' => $data->portions, 'heart_price' => $data->heartPrice];
        Validator::make([...$payload, 'operation_key' => $data->operationKey, 'expected_version' => $data->expectedVersion], ['title' => ['required', 'string', 'max:120'], 'operation_key' => ['required', 'uuid'], 'expected_version' => ['integer', 'min:0'], 'ingredients' => ['required', 'string', 'max:2000'], 'portions' => ['integer', sprintf('between:1,%d', Dish::MAXIMUM_PORTIONS)], 'heart_price' => ['integer', sprintf('between:0,%d', Dish::MAXIMUM_HEART_PRICE)]])->validate();
        if (($data->kind === DishKind::Ordinary && $data->heartPrice !== 0) || ($data->kind === DishKind::Special && $data->heartPrice !== 0 && $data->heartPrice < Dish::MINIMUM_HEART_PRICE)) {
            Log::warning('dish.change_denied', ['actor_id' => $actor->id, 'reason' => 'invalid_heart_price']);
            throw ValidationException::withMessages(['heart_price' => __('Обычное блюдо бесплатно. Особое: бесплатно или от 5 до 50 сердечек.')]);
        }
        $photoHash = $data->photo === null ? null : hash('sha256', $data->photo->getContent());
        $path = $this->photos->store($data->photo);
        try {
            $saved = DB::transaction(function () use ($actor, $data, $item, $payload, $photoHash, $path): Dish {
                User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $current = $item === null ? Dish::query()->where('user_id', $actor->id)->where('operation_key', $data->operationKey)->lockForUpdate()->first() : Dish::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
                if ($current !== null) {
                    Gate::forUser($actor)->authorize('update', $current);
                    if ($this->versions->isRepeated($current, $data->operationKey, $item === null, $payload, $photoHash)) {
                        return $current;
                    }
                    $this->versions->requireChange($current, $data->expectedVersion, $item === null);
                }
                if ($current === null) {
                    $current = new Dish;
                    $current->operation_key = $data->operationKey;
                }
                $current->forceFill([...$payload, 'user_id' => $actor->id, 'last_operation_key' => $data->operationKey, 'version' => $item === null ? 0 : $current->version + 1]);
                if ($path !== null) {
                    $current->forceFill(['photo_path' => $path, 'photo_hash' => $photoHash]);
                }
                $current->save();
                DB::afterCommit(fn () => Log::info('catalog.item_saved', ['type' => 'Dish', 'item_id' => $current->id, 'actor_id' => $actor->id, 'version' => $current->version]));

                return $current;
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->photos->discard($path);
            throw $exception;
        }
        if ($path !== null && $saved->photo_path !== $path) {
            $this->photos->discard($path);
        }

        return $saved;
    }
}
