<?php

declare(strict_types=1);

namespace App\Actions\Dates;

use App\Actions\Requests\CreatePartnerRequest;
use App\Data\Dates\DateInvitationData;
use App\Data\Requests\NewRequestData;
use App\Enums\RequestType;
use App\Models\Couple;
use App\Models\PartnerRequest;
use App\Models\User;

final class CreateDateInvitation
{
    public function __construct(private readonly CreatePartnerRequest $requests) {}

    public function execute(User $actor, Couple $couple, DateInvitationData $data): PartnerRequest
    {
        return $this->requests->execute($actor, $couple, new NewRequestData(type: RequestType::Event, description: $data->description ?? $data->title, dueAt: $data->startsAt, operationKey: $data->operationKey, templateId: $data->templateId, dateInvitation: $data));
    }
}
