<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MoodPrompt;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class MoodPromptPolicy
{
    public function view(User $actor, MoodPrompt $item): bool
    {
        return Gate::forUser($actor)->allows('view', $item->couple);
    }
}
