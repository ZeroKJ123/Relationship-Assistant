<?php

declare(strict_types=1);

namespace App\Actions\Requests;

use App\Data\Requests\RequestResponseData;
use App\Enums\DeclineReason;
use App\Enums\HeartOperationType;
use App\Enums\RequestResponse;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Couple;
use App\Models\PartnerRequest;
use App\Models\RequestCondition;
use App\Models\RequestEvent;
use App\Models\User;
use App\Services\Catalogs\RequestCatalog;
use App\Services\Duties\CreateAcceptedDuties;
use App\Services\Hearts\Ledger;
use App\Services\Hearts\ServicePayments;
use App\Services\Requests\AgreementAttachments;
use App\Services\Requests\ConditionAttachments;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

final class RespondToRequest
{
    public function __construct(private readonly RequestContext $context, private readonly CreateAcceptedDuties $duties, private readonly Ledger $ledger, private readonly ServicePayments $payments, private readonly RequestCatalog $catalog, private readonly AgreementAttachments $attachments, private readonly ConditionAttachments $conditions) {}

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
            if ($current->status === RequestStatus::Accepted) {
                $this->attachments->accept($current);
                $this->payments->reserve($current);
                $this->duties->execute($current);
            }
            if ($current->status === RequestStatus::Declined && $current->decline_penalty > 0) {
                $this->ledger->apply($actor, -$current->decline_penalty, HeartOperationType::RefusalPenalty, sprintf('request:%d:refusal', $current->id), coupleId: $current->couple_id, actorId: $actor->id);
            }

            return $this->context->record($actor, $current, $data->response->value);
        }, attempts: 3);
        if ($event !== null) {
            $this->context->logEvent($event);
        }
    }

    private function applyResponse(User $actor, PartnerRequest $request, RequestResponseData $data): void
    {
        if ($data->response === RequestResponse::Conditional) {
            if ($request->moneyRequest()->exists()) {
                $this->context->deny('money_accept_or_decline_only', requestId: $request->id);
            }
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
        $snapshot = $this->catalog->conditionSnapshot($actor, $data);
        (new RequestCondition)->forceFill([...$this->conditions->snapshot($actor, $snapshot['type'], $data), 'partner_request_id' => $request->id, 'proposed_by_id' => $actor->id, 'version' => $request->version + 1, 'type' => $snapshot['type'], 'description' => $snapshot['description'], 'method_kind' => $snapshot['method_kind'], 'method_name' => $snapshot['method_name'], 'sushi_size' => $snapshot['sushi_size'], 'due_at' => $data->dueAt?->utc()])->save();
        $request->status = RequestStatus::Conditional;
    }
}
