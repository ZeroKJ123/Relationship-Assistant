<?php

declare(strict_types=1);

namespace App\Actions\Rewards;

use App\Models\Reward;
use App\Models\User;
use App\Services\Catalogs\CatalogVersions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class ArchiveReward
{
    public function __construct(private readonly CatalogVersions $versions) {}

    public function execute(User $actor, Reward $reward, int $version): void
    {
        Gate::forUser($actor)->authorize('update', $reward);
        DB::transaction(function () use ($actor, $reward, $version): void {
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $current = Reward::query()->whereKey($reward->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $current);
            if ($current->archived_at !== null) {
                return;
            }
            $this->versions->requireChange($current, $version, false);
            $current->forceFill(['archived_at' => now(), 'version' => $current->version + 1])->save();
            DB::afterCommit(fn () => Log::info('reward.archived', ['reward_id' => $current->id, 'actor_id' => $actor->id]));
        }, attempts: 3);
    }
}
