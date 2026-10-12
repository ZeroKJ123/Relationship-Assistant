<?php

declare(strict_types=1);

namespace App\Actions\Profiles;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

final class SaveCompliments
{
    /** @param list<string> $texts */
    public function execute(User $actor, array $texts): void
    {
        Validator::make(['compliments' => $texts], ['compliments' => ['array', 'max:50'], 'compliments.*' => ['string', 'max:300']])->validate();
        $texts = array_values(array_unique(array_filter(array_map(trim(...), $texts), fn (string $text): bool => $text !== '')));
        DB::transaction(function () use ($actor, $texts): void {
            $current = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $current->forceFill(['compliments' => $texts])->save();
            DB::afterCommit(fn () => Log::info('profile.compliments_saved', ['actor_id' => $actor->id, 'count' => count($texts)]));
        }, attempts: 3);
    }
}
