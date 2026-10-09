<?php

declare(strict_types=1);

namespace App\Actions\Requests;

use App\Data\Requests\NewRequestData;
use App\Enums\RequestStatus;
use App\Models\Couple;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Services\Requests\RefusalTariff;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class CreatePartnerRequest
{
    public function __construct(private readonly RequestContext $context, private readonly RefusalTariff $tariff) {}

    public function execute(User $actor, Couple $couple, NewRequestData $data): PartnerRequest
    {
        Log::debug('request.creation_started', ['actor_id' => $actor->id, 'couple_id' => $couple->id]);
        $operationKey = $data->operationKey ?? Str::uuid()->toString();
        Validator::make(['operation_key' => $operationKey, 'description' => $data->description, 'due_at' => $data->dueAt], ['operation_key' => ['required', 'uuid'], 'description' => ['required', 'string', 'max:2000'], 'due_at' => ['required', 'date', 'after:now']])->validate();
        [$request, $event] = DB::transaction(function () use ($actor, $couple, $data, $operationKey): array {
            $current = $this->context->lockCouple($actor, $couple);
            $existing = PartnerRequest::query()->where('author_id', $actor->id)->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->couple_id !== $current->id || $existing->type !== $data->type || $existing->description !== $data->description || ! $existing->due_at->equalTo($data->dueAt) || $existing->is_very_wanted !== $data->isVeryWanted) {
                    $this->context->deny('changed_creation_payload', coupleId: $current->id);
                }

                return [$existing, null];
            }
            if (! $data->dueAt->isFuture()) {
                $this->context->deny('expired_deadline', coupleId: $current->id);
            }
            if ($data->isVeryWanted && $current->agreement()->lockForUpdate()->first()?->active_weekly_limit === null) {
                $this->context->deny('missing_weekly_agreement', coupleId: $current->id);
            }
            $recipient = $current->members()->where('user_id', '!=', $actor->id)->lockForUpdate()->sole();
            $request = new PartnerRequest;
            $request->forceFill([
                'operation_key' => $operationKey,
                'couple_id' => $current->id,
                'author_id' => $actor->id,
                'recipient_id' => $recipient->user_id,
                'type' => $data->type,
                'description' => $data->description,
                'due_at' => $data->dueAt->utc(),
                'is_very_wanted' => $data->isVeryWanted,
                'refusal_tariff_version' => $this->tariff->getVersion(),
                'refusal_tariff' => $data->isVeryWanted ? $this->tariff->getAmount() : 0,
                'status' => RequestStatus::Pending,
                'version' => 0,
            ])->save();

            return [$request, $this->context->record($actor, $request, 'created')];
        });
        if ($event !== null) {
            $this->context->logEvent($event);
        }

        return $request;
    }
}
