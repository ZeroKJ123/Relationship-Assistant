<?php

declare(strict_types=1);

namespace App\Actions\Requests;

use App\Enums\RequestStatus;
use App\Models\Couple;
use App\Models\PartnerRequest;
use App\Models\RequestEvent;
use App\Models\User;
use App\Services\Duties\CreateAcceptedDuties;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ResolveCondition
{
    public function __construct(private readonly RequestContext $context, private readonly CreateAcceptedDuties $duties) {}

    public function execute(User $actor, Couple $couple, PartnerRequest $request, int $version, bool $isAccepted = true): void
    {
        Log::debug('request.condition_review_started', ['actor_id' => $actor->id, 'request_id' => $request->id, 'expected_version' => $version]);
        $event = DB::transaction(function () use ($actor, $couple, $request, $version, $isAccepted): ?RequestEvent {
            $current = $this->context->lockRequest($actor, $couple, $request, 'acceptCondition');
            $condition = $current->conditions()->where('version', $version)->lockForUpdate()->firstOrFail();
            if ($isAccepted && $current->status === RequestStatus::Accepted && $current->version === $version + 1 && $condition->accepted_at !== null) {
                return null;
            }
            if (! $isAccepted && $current->status === RequestStatus::Declined && $current->version === $version + 1 && $current->decline_reason === null) {
                return null;
            }
            $this->context->requireVersion($current, $version);
            if ($current->status !== RequestStatus::Conditional) {
                $this->context->deny('invalid_status', requestId: $current->id);
            }
            if (! $isAccepted) {
                $current->forceFill(['status' => RequestStatus::Declined, 'decline_penalty' => 0, 'version' => $current->version + 1])->save();

                return $this->context->record($actor, $current, 'condition_declined');
            }
            if (! $condition->due_at->isFuture() || ! $current->due_at->isFuture()) {
                $this->context->deny('expired_deadline', requestId: $current->id);
            }
            $condition->forceFill(['accepted_at' => now()])->save();
            $current->forceFill(['status' => RequestStatus::Accepted, 'accepted_at' => now(), 'version' => $current->version + 1])->save();

            $this->duties->execute($current);

            return $this->context->record($actor, $current, 'condition_accepted');
        }, attempts: 3);
        if ($event !== null) {
            $this->context->logEvent($event);
        }
    }
}
