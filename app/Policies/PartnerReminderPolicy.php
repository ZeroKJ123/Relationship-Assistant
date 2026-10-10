<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PartnerReminder;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class PartnerReminderPolicy
{
    public function view(User $actor, PartnerReminder $item): bool
    {
        return Gate::forUser($actor)->allows('view', $item->couple);
    }

    public function cancel(User $actor, PartnerReminder $item): bool
    {
        return $actor->id === $item->author_id && $this->view($actor, $item);
    }
}
