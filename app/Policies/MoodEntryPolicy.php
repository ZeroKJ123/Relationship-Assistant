<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MoodEntry;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class MoodEntryPolicy
{
    public function view(User $actor, MoodEntry $item): bool
    {
        return Gate::forUser($actor)->allows('view', $item->couple);
    }
}
