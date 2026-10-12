<?php

declare(strict_types=1);

namespace App\Actions\Profiles;

use App\Data\Profiles\UpdateProfileData;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class UpdateProfile
{
    public function execute(User $actor, User $user, UpdateProfileData $data): void
    {
        Gate::forUser($actor)->authorize('update', $user);
        if (! $data->settingsOnly && ($data->name === null || $data->gender === null)) {
            throw ValidationException::withMessages(['name' => __('Укажите имя и пол.')]);
        }
        $fields = DB::transaction(function () use ($actor, $user, $data): array {
            $current = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $current);
            if (! $data->settingsOnly) {
                $current->fill(['name' => $data->name, 'gender' => $data->gender]);
                if ($data->bioProvided) {
                    $current->bio = $data->bio;
                }
            }
            if ($data->paymentProvided) {
                $current->payment_details = $data->paymentDetails;
            }
            if ($data->currency !== null) {
                $current->currency = $data->currency;
            }
            $changed = array_keys($current->getDirty());
            $current->save();

            return $changed;
        }, attempts: 3);
        Log::info('profile.updated', ['user_id' => $user->id, 'fields' => $fields]);
    }
}
