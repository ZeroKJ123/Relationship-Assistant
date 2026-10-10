<?php

declare(strict_types=1);

namespace App\Actions\Requests;

use App\Data\Requests\GuestVisitData;
use App\Data\Requests\NewRequestData;
use App\Enums\RequestType;
use App\Models\Couple;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Services\Requests\SocialInvitations;

final class CreateGuestVisit
{
    public function __construct(private readonly CreatePartnerRequest $requests, private readonly SocialInvitations $invitations) {}

    public function execute(User $actor, Couple $couple, GuestVisitData $data): PartnerRequest
    {
        return $this->requests->execute($actor, $couple, new NewRequestData(type: RequestType::Guests, description: $data->reason, dueAt: $this->invitations->getEndsAt($data), operationKey: $data->operationKey, templateId: $data->templateId, guestVisit: $data));
    }
}
