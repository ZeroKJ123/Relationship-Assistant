<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Enums\Currency;
use App\Enums\DishKind;
use App\Models\Dish;
use App\Models\Friend;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class CatalogVersions
{
    /** @param array<string, int|string|Currency|DishKind|null> $payload */
    public function isRepeated(Dish|WishlistItem|Friend $item, string $operationKey, bool $isCreation, array $payload, ?string $photoHash): bool
    {
        if (($isCreation ? $item->operation_key : $item->last_operation_key) !== $operationKey) {
            return false;
        }
        foreach ($payload as $key => $value) {
            if ($item->getAttribute($key) !== $value) {
                $this->deny($item);
            }
        }
        if ($photoHash !== null && ! $item instanceof Friend && $item->photo_hash !== $photoHash) {
            $this->deny($item);
        }

        return true;
    }

    public function requireChange(Dish|WishlistItem|Friend $item, int $expectedVersion, bool $isCreation): void
    {
        if ($isCreation || $item->version !== $expectedVersion) {
            $this->deny($item);
        }
        if ($item instanceof WishlistItem && ($item->archived_at !== null || $item->received_at !== null)) {
            $this->deny($item);
        }
        if ($item instanceof Friend && $item->archived_at !== null) {
            $this->deny($item);
        }
    }

    private function deny(Dish|WishlistItem|Friend $item): never
    {
        Log::warning('catalog.change_denied', ['type' => $item::class, 'item_id' => $item->id, 'reason' => 'stale_or_changed_payload']);
        throw ValidationException::withMessages(['expected_version' => __('Карточка изменилась. Обновите страницу и повторите действие.')]);
    }
}
