<?php

declare(strict_types=1);

namespace App\Actions\Wishlist;

use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class ArchiveWishlistItem
{
    public function execute(User $actor, WishlistItem $item, int $version): void
    {
        Gate::forUser($actor)->authorize('update', $item);
        DB::transaction(function () use ($actor, $item, $version): void {
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $current = WishlistItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $current);
            if ($current->archived_at !== null) {
                return;
            }
            if ($current->version !== $version || $current->claimed_condition_id !== null) {
                throw ValidationException::withMessages(['expected_version' => __('Желание изменилось или уже обещано в принятом условии.')]);
            }
            $current->forceFill(['archived_at' => now(), 'version' => $current->version + 1])->save();
            DB::afterCommit(fn () => Log::info('wishlist.archived', ['item_id' => $current->id, 'actor_id' => $actor->id]));
        }, attempts: 3);
    }
}
