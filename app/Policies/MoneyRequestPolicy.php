<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MoneyRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class MoneyRequestPolicy
{
    public function view(User $actor, MoneyRequest $moneyRequest): bool
    {
        return Gate::forUser($actor)->allows('view', $moneyRequest->partnerRequest);
    }
}
