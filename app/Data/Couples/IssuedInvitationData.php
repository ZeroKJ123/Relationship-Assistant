<?php

declare(strict_types=1);

namespace App\Data\Couples;

final readonly class IssuedInvitationData
{
    public function __construct(public int $coupleId, public int $invitationId, public string $token) {}
}
