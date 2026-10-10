<?php

declare(strict_types=1);

namespace App\Data\Dates;

use App\Enums\InvitationKind;
use Carbon\CarbonImmutable;

final readonly class DateInvitationData
{
    public function __construct(public InvitationKind $kind, public string $title, public CarbonImmutable $startsAt, public ?string $description, public ?string $dressCode, public string $operationKey, public ?int $templateId = null) {}
}
