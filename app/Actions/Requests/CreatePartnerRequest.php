<?php

declare(strict_types=1);

namespace App\Actions\Requests;

use App\Data\Requests\NewRequestData;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Couple;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Services\Catalogs\RequestCatalog;
use App\Services\Hearts\EconomyRules;
use App\Services\Hearts\ServicePayments;
use App\Services\Requests\RefusalTariff;
use App\Services\Requests\RequestAttachments;
use App\Services\Requests\RequestContext;
use App\Services\Requests\VeryWantedRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class CreatePartnerRequest
{
    public function __construct(private readonly RequestContext $context, private readonly RefusalTariff $tariff, private readonly VeryWantedRequests $veryWanted, private readonly ServicePayments $payments, private readonly RequestCatalog $catalog, private readonly RequestAttachments $attachments) {}

    public function execute(User $actor, Couple $couple, NewRequestData $data): PartnerRequest
    {
        Log::debug('request.creation_started', ['actor_id' => $actor->id, 'couple_id' => $couple->id]);
        $dueAt = $data->dueAt->setMicrosecond(0);
        $operationKey = $data->operationKey ?? Str::uuid()->toString();
        Validator::make(['heart_price' => $data->heartPrice, 'operation_key' => $operationKey, 'description' => $data->description, 'due_at' => $dueAt], ['heart_price' => ['integer', sprintf('between:0,%d', EconomyRules::MAXIMUM_SETTING)], 'operation_key' => ['required', 'uuid'], 'description' => ['required', 'string', 'max:2000'], 'due_at' => ['required', 'date', 'after:now']])->validate();
        [$request, $event] = DB::transaction(function () use ($actor, $couple, $data, $operationKey, $dueAt): array {
            $current = $this->context->lockCouple($actor, $couple);
            $existing = PartnerRequest::query()->where('author_id', $actor->id)->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if (! $this->attachments->isSame($existing, $data) || $existing->couple_id !== $current->id || $existing->type !== $data->type || $existing->description !== $data->description || ! $existing->due_at->equalTo($dueAt) || $existing->is_very_wanted !== $data->isVeryWanted || $existing->heart_price !== $data->heartPrice || $existing->request_template_id !== $data->templateId || $existing->expense_category_id !== $data->expenseCategoryId) {
                    $this->context->deny('changed_creation_payload', coupleId: $current->id);
                }

                return [$existing, null];
            }
            if (! $dueAt->isFuture()) {
                $this->context->deny('expired_deadline', coupleId: $current->id);
            }
            if ($data->isVeryWanted) {
                $this->veryWanted->requireAvailability($actor, $current);
            }
            if ($data->heartPrice > 0 && $data->type !== RequestType::Service && ($data->type !== RequestType::Food || $data->dishId === null)) {
                $this->context->deny('unsupported_paid_request', coupleId: $current->id);
            }
            $this->payments->requireAvailability($actor, $data->heartPrice);
            $recipient = $current->members()->where('user_id', '!=', $actor->id)->lockForUpdate()->sole();
            $request = new PartnerRequest;
            $request->forceFill([
                ...$this->catalog->requestSnapshot($data),
                ...$this->attachments->snapshot($actor, $recipient->user_id, $data),
                'heart_price' => $data->heartPrice,
                'operation_key' => $operationKey,
                'couple_id' => $current->id,
                'author_id' => $actor->id,
                'recipient_id' => $recipient->user_id,
                'type' => $data->type,
                'description' => $data->description,
                'due_at' => $dueAt->utc(),
                'is_very_wanted' => $data->isVeryWanted,
                'refusal_tariff_version' => $this->tariff->getVersion(),
                'refusal_tariff' => $data->isVeryWanted ? $this->tariff->getAmount() : 0,
                'status' => RequestStatus::Pending,
                'version' => 0,
            ])->save();

            $this->attachments->attachMoney($request, $data);

            return [$request, $this->context->record($actor, $request, 'created')];
        }, attempts: 3);
        if ($event !== null) {
            $this->context->logEvent($event);
        }

        return $request;
    }
}
