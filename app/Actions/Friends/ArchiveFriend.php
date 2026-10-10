<?php

declare(strict_types=1);

namespace App\Actions\Friends;

use App\Models\Friend;
use App\Models\User;
use App\Services\Catalogs\CatalogVersions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class ArchiveFriend
{
    public function __construct(private readonly CatalogVersions $versions) {}

    public function execute(User $actor, Friend $friend, int $version): void
    {
        Gate::forUser($actor)->authorize('update', $friend);
        DB::transaction(function () use ($actor, $friend, $version): void {
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $current = Friend::query()->whereKey($friend->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $current);
            if ($current->archived_at !== null) {
                return;
            }
            $this->versions->requireChange($current, $version, false);
            $current->forceFill(['archived_at' => now(), 'version' => $current->version + 1])->save();
            DB::afterCommit(fn () => Log::info('friend.archived', ['friend_id' => $current->id, 'actor_id' => $actor->id]));
        }, attempts: 3);
    }
}
