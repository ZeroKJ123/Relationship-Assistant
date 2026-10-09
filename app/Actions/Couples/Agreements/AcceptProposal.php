<?php

declare(strict_types=1);

namespace App\Actions\Couples\Agreements;

use App\Enums\AgreementProposalStatus;
use App\Models\AgreementApproval;
use App\Models\AgreementProposal;
use App\Models\Couple;
use App\Models\User;
use App\Services\Couples\AgreementContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class AcceptProposal
{
    private const PARTNER_COUNT = 2;

    public function __construct(private readonly AgreementContext $context) {}

    public function execute(User $actor, Couple $couple, AgreementProposal $proposal): void
    {
        Log::debug('agreement.approval_started', ['actor_id' => $actor->id, 'proposal_id' => $proposal->id]);
        $isActivated = DB::transaction(function () use ($actor, $couple, $proposal): bool {
            $agreement = $this->context->lockForUpdate($actor, $couple);
            $current = $agreement->proposals()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            $this->context->requireVersion($agreement, $current->version);
            if ($current->status === AgreementProposalStatus::Accepted) {
                return false;
            }
            if ($current->status !== AgreementProposalStatus::Proposed) {
                throw ValidationException::withMessages(['agreement' => __('Этот вариант больше нельзя подтвердить. Рассмотрите текущий вариант.')]);
            }
            // Locking reads see approvals committed while this transaction waited.
            $approvals = $current->approvals()->lockForUpdate()->get();
            $count = $approvals->count();
            if (! $approvals->contains('user_id', $actor->id)) {
                (new AgreementApproval)->forceFill(['agreement_proposal_id' => $current->id, 'user_id' => $actor->id])->save();
                $count++;
            }
            if ($count !== self::PARTNER_COUNT) {
                return false;
            }
            $current->forceFill(['status' => AgreementProposalStatus::Accepted, 'accepted_at' => now()])->save();
            $agreement->forceFill(['active_version' => $current->version, 'active_weekly_limit' => $current->weekly_limit])->save();

            return true;
        });
        if ($isActivated) {
            Log::info('agreement.activated', ['couple_id' => $couple->id, 'proposal_id' => $proposal->id, 'version' => $proposal->version]);
        }
        Log::info('agreement.proposal_approved', ['actor_id' => $actor->id, 'couple_id' => $couple->id, 'proposal_id' => $proposal->id, 'version' => $proposal->version]);
    }
}
