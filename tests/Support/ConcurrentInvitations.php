<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;

final class ConcurrentInvitations
{
    /**
     * @param  list<array{actor: User, token: string}>  $attempts
     * @return list<string>
     */
    public function run(array $attempts): array
    {
        $arguments = array_map(fn (array $attempt): array => [(string) $attempt['actor']->id, $attempt['token']], $attempts);

        return (new ConcurrentOperations)->run('accept-invitation-worker.php', $arguments);
    }
}
