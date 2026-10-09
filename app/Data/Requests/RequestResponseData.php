<?php

declare(strict_types=1);

namespace App\Data\Requests;

use App\Enums\ConditionType;
use App\Enums\DeclineReason;
use App\Enums\RequestResponse;
use Carbon\CarbonImmutable;

final readonly class RequestResponseData
{
    public function __construct(public RequestResponse $response, public int $expectedVersion, public ?DeclineReason $reason = null, public ?ConditionType $conditionType = null, public ?string $description = null, public ?CarbonImmutable $dueAt = null, public ?int $methodId = null, public ?string $sushiSize = null) {}
}
