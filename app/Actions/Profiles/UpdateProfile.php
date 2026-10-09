<?php

declare(strict_types=1);

namespace App\Actions\Profiles;

use App\Enums\Gender;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class UpdateProfile
{
    public function execute(User $actor, User $user, string $name, Gender $gender, ?string $paymentDetails): void
    {
        Gate::forUser($actor)->authorize('update', $user);
        $user->fill(['name' => $name, 'gender' => $gender, 'payment_details' => $paymentDetails]);
        $fields = array_keys($user->getDirty());
        $user->save();
        Log::info('profile.updated', ['user_id' => $user->id, 'fields' => $fields]);
    }
}
