<?php

declare(strict_types=1);

namespace App\Actions\Wishlist;

use App\Data\Wishlist\WishlistItemData;
use App\Models\User;
use App\Models\WishlistItem;
use App\Services\Catalogs\CatalogVersions;
use App\Services\Media\PrivatePhotos;
use App\Services\Money\DecimalAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

final class SaveWishlistItem
{
    public function __construct(private readonly DecimalAmount $amounts, private readonly PrivatePhotos $photos, private readonly CatalogVersions $versions) {}

    public function execute(User $actor, WishlistItemData $data, ?WishlistItem $item = null): WishlistItem
    {
        if ($item !== null) {
            Gate::forUser($actor)->authorize('update', $item);
        }
        if ($item === null && $data->expectedVersion !== 0) {
            throw ValidationException::withMessages(['expected_version' => __('Новая карточка должна начинаться с исходной версии.')]);
        }
        $amount = $this->amounts->normalize($data->price, 'price');
        $payload = ['title' => $data->title, 'description' => $data->description, 'price' => $amount, 'currency' => $data->currency];
        Validator::make([...$payload, 'operation_key' => $data->operationKey, 'expected_version' => $data->expectedVersion], ['title' => ['required', 'string', 'max:120'], 'operation_key' => ['required', 'uuid'], 'expected_version' => ['integer', 'min:0'], 'description' => ['nullable', 'string', 'max:2000']])->validate();

        $photoHash = $data->photo === null ? null : hash('sha256', $data->photo->getContent());
        $path = $this->photos->store($data->photo);
        try {
            $saved = DB::transaction(function () use ($actor, $data, $item, $payload, $photoHash, $path): WishlistItem {
                User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $current = $item === null ? WishlistItem::query()->where('user_id', $actor->id)->where('operation_key', $data->operationKey)->lockForUpdate()->first() : WishlistItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
                if ($current !== null) {
                    Gate::forUser($actor)->authorize('update', $current);
                    if ($this->versions->isRepeated($current, $data->operationKey, $item === null, $payload, $photoHash)) {
                        return $current;
                    }
                    $this->versions->requireChange($current, $data->expectedVersion, $item === null);
                }
                if ($current === null) {
                    $current = new WishlistItem;
                    $current->operation_key = $data->operationKey;
                }
                $current->forceFill([...$payload, 'user_id' => $actor->id, 'last_operation_key' => $data->operationKey, 'version' => $item === null ? 0 : $current->version + 1]);
                if ($path !== null) {
                    $current->forceFill(['photo_path' => $path, 'photo_hash' => $photoHash]);
                }
                $current->save();
                DB::afterCommit(fn () => Log::info('catalog.item_saved', ['type' => 'WishlistItem', 'item_id' => $current->id, 'actor_id' => $actor->id, 'version' => $current->version]));

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
