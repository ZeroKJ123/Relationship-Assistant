<?php

declare(strict_types=1);

namespace App\Actions\Profiles;

use App\Enums\Gender;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class UpdateProfile
{
    public function execute(User $actor, User $user, string $name, Gender $gender, ?string $paymentDetails): void
    {
        Gate::forUser($actor)->authorize('update', $user);
        $fields = DB::transaction(function () use ($actor, $user, $name, $gender, $paymentDetails): array {
            $current = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $current);
            $current->fill(['name' => $name, 'gender' => $gender, 'payment_details' => $paymentDetails]);
            $changed = array_keys($current->getDirty());
            $current->save();

            return $changed;
        }, attempts: 3);
        Log::info('profile.updated', ['user_id' => $user->id, 'fields' => $fields]);
    }
}
