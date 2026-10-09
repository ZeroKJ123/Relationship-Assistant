<?php

declare(strict_types=1);

namespace App\Actions\Requests;

use App\Enums\DutyStatus;
use App\Enums\RequestStatus;
use App\Models\Couple;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Services\Hearts\ServicePayments;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\DB;

final class CancelPartnerRequest
{
    public function __construct(private readonly RequestContext $context, private readonly ServicePayments $payments) {}

    public function execute(User $actor, Couple $couple, PartnerRequest $request, int $version): void
    {
        DB::transaction(function () use ($actor, $couple, $request, $version): void {
            $current = $this->context->lockRequest($actor, $couple, $request, 'view');
            if ($current->version === $version + 1 && $current->events()->where('version', $current->version)->where('actor_id', $actor->id)->whereIn('type', ['cancellation_proposed', 'cancelled'])->lockForUpdate()->first() !== null) {
                return;
            }
            $this->context->requireVersion($current, $version);
            if (! in_array($current->status, [RequestStatus::Pending, RequestStatus::Conditional, RequestStatus::Accepted], true)) {
                $this->context->deny('not_cancellable', requestId: $current->id);
            }
            if ($current->status !== RequestStatus::Accepted && $actor->id !== $current->author_id) {
                $this->context->deny('only_author_can_cancel_unaccepted', requestId: $current->id);
            }
            if ($current->duties()->where('status', DutyStatus::Confirmed)->lockForUpdate()->first() !== null) {
                $this->context->deny('already_completed', requestId: $current->id);
            }
            if ($current->status === RequestStatus::Accepted && $current->cancellation_proposed_by_id === null) {
                $current->forceFill(['cancellation_proposed_by_id' => $actor->id, 'version' => $current->version + 1])->save();
                $event = $this->context->record($actor, $current, 'cancellation_proposed');
                DB::afterCommit(fn () => $this->context->logEvent($event));

                return;
            }
            if ($current->status === RequestStatus::Accepted && $current->cancellation_proposed_by_id === $actor->id) {
                $this->context->deny('needs_partner_cancellation', requestId: $current->id);
            }
            $this->payments->release($current);
            $current->duties()->whereIn('status', [DutyStatus::Pending, DutyStatus::Overdue, DutyStatus::Reported])->update(['status' => DutyStatus::Cancelled]);
            $current->forceFill(['status' => RequestStatus::Cancelled, 'version' => $current->version + 1])->save();
            $event = $this->context->record($actor, $current, 'cancelled');
            DB::afterCommit(fn () => $this->context->logEvent($event));
        }, attempts: 3);
    }
}
