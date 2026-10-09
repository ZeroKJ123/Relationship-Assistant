<?php

declare(strict_types=1);

namespace App\Actions\Couples\Agreements;

use App\Enums\AgreementProposalStatus;
use App\Models\AgreementProposal;
use App\Models\Couple;
use App\Models\User;
use App\Services\Couples\AgreementContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class DeclineProposal
{
    public function __construct(private readonly AgreementContext $context) {}

    public function execute(User $actor, Couple $couple, AgreementProposal $proposal): void
    {
        Log::debug('agreement.decline_started', ['actor_id' => $actor->id, 'proposal_id' => $proposal->id]);
        DB::transaction(function () use ($actor, $couple, $proposal): void {
            $agreement = $this->context->lockForUpdate($actor, $couple);
            $current = $agreement->proposals()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            $this->context->requireVersion($agreement, $current->version);
            if ($current->status === AgreementProposalStatus::Declined) {
                return;
            }
            if ($current->status !== AgreementProposalStatus::Proposed) {
                throw ValidationException::withMessages(['agreement' => __('Согласованное правило нельзя отменить одному. Предложите новый вариант.')]);
            }
            $current->forceFill(['status' => AgreementProposalStatus::Declined])->save();
        });
        Log::info('agreement.proposal_declined', ['actor_id' => $actor->id, 'couple_id' => $couple->id, 'proposal_id' => $proposal->id]);
    }
}
