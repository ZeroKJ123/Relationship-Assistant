<?php

declare(strict_types=1);

namespace App\Data\Requests;

use App\Data\Dates\DateInvitationData;
use App\Enums\Currency;
use App\Enums\RequestType;
use Carbon\CarbonImmutable;

final readonly class NewRequestData
{
    public function __construct(public RequestType $type, public string $description, public CarbonImmutable $dueAt, public bool $isVeryWanted = false, public ?string $operationKey = null, public int $heartPrice = 0, public ?int $templateId = null, public ?int $expenseCategoryId = null, public ?int $dishId = null, public ?int $dishVersion = null, public ?string $moneyAmount = null, public ?Currency $moneyCurrency = null, public ?GuestVisitData $guestVisit = null, public ?DateInvitationData $dateInvitation = null) {}
}
