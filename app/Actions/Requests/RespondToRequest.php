<?php

declare(strict_types=1);

namespace App\Actions\Requests;

use App\Data\Requests\RequestResponseData;
use App\Enums\DeclineReason;
use App\Enums\RequestResponse;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Couple;
use App\Models\PartnerRequest;
use App\Models\RequestCondition;
use App\Models\RequestEvent;
use App\Models\User;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

final class RespondToRequest
{
    public function __construct(private readonly RequestContext $context) {}

    public function execute(User $actor, Couple $couple, PartnerRequest $request, RequestResponseData $data): void
    {
        Log::debug('request.response_started', ['actor_id' => $actor->id, 'request_id' => $request->id, 'expected_version' => $data->expectedVersion]);
        $event = DB::transaction(function () use ($actor, $couple, $request, $data): ?RequestEvent {
            $current = $this->context->lockRequest($actor, $couple, $request, 'respond');
            // Repeated final responses are idempotent only for the exact previous action.
            if ($current->version === $data->expectedVersion + 1 && $this->isRepeatedFinalResponse($current, $data)) {
                return null;
            }
            $this->context->requireVersion($current, $data->expectedVersion);
            $this->applyResponse($actor, $current, $data);
            $current->version++;
            $current->save();

            return $this->context->record($actor, $current, $data->response->value);
        });
        if ($event !== null) {
            $this->context->logEvent($event);
        }
    }

    private function applyResponse(User $actor, PartnerRequest $request, RequestResponseData $data): void
    {
        if ($data->response === RequestResponse::Conditional) {
            $this->proposeCondition($actor, $request, $data);

            return;
        }
        if ($request->status !== RequestStatus::Pending) {
            $this->context->deny('invalid_status', requestId: $request->id);
        }
        if ($data->response === RequestResponse::Agree && ! $request->due_at->isFuture()) {
            $this->context->deny('expired_deadline', requestId: $request->id);
        }
        $this->respondWithoutCondition($request, $data);
    }

    private function isRepeatedFinalResponse(PartnerRequest $request, RequestResponseData $data): bool
    {
        return ($data->response === RequestResponse::Agree && $request->status === RequestStatus::Accepted && ! $request->conditions()->lockForUpdate()->exists())
            || ($data->response === RequestResponse::Decline && $request->status === RequestStatus::Declined && $data->reason === $request->decline_reason);
    }

    private function respondWithoutCondition(PartnerRequest $request, RequestResponseData $data): void
    {
        if ($data->response === RequestResponse::Agree) {
            $request->forceFill(['status' => RequestStatus::Accepted, 'accepted_at' => now()]);

            return;
        }
        if ($data->reason === null || ($data->reason === DeclineReason::NoFoodMoney && $request->type !== RequestType::Food)) {
            $this->context->deny('invalid_decline_reason', requestId: $request->id);
        }
        $request->forceFill(['status' => RequestStatus::Declined, 'decline_reason' => $data->reason, 'decline_penalty' => $data->reason === DeclineReason::NoFoodMoney ? 0 : $request->refusal_tariff]);
    }

    private function proposeCondition(User $actor, PartnerRequest $request, RequestResponseData $data): void
    {
        if (! in_array($request->status, [RequestStatus::Pending, RequestStatus::Conditional], true)) {
            $this->context->deny('invalid_status', requestId: $request->id);
        }
        if (! $request->due_at->isFuture()) {
            $this->context->deny('expired_deadline', requestId: $request->id);
        }
        Validator::make(['type' => $data->conditionType?->value, 'description' => $data->description, 'due_at' => $data->dueAt], ['type' => ['required'], 'description' => ['required', 'string', 'max:2000'], 'due_at' => ['required', 'date', 'after:now']])->validate();
        (new RequestCondition)->forceFill(['partner_request_id' => $request->id, 'proposed_by_id' => $actor->id, 'version' => $request->version + 1, 'type' => $data->conditionType, 'description' => $data->description, 'due_at' => $data->dueAt?->utc()])->save();
        $request->status = RequestStatus::Conditional;
    }
}
