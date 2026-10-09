<?php

declare(strict_types=1);

namespace App\Services\Couples;

use App\Enums\AgreementProposalStatus;
use App\Models\AgreementProposal;
use App\Models\CoupleAgreement;
use App\Models\User;
use Illuminate\Support\Facades\Log;

final class AgreementProposals
{
    public function create(CoupleAgreement $agreement, int $weeklyLimit, ?User $author): AgreementProposal
    {
        $agreement->proposals()->where('status', AgreementProposalStatus::Proposed->value)->update(['status' => AgreementProposalStatus::Superseded->value]);
        $version = $agreement->current_version + 1;
        $proposal = new AgreementProposal;
        $proposal->forceFill(['couple_agreement_id' => $agreement->id, 'version' => $version, 'weekly_limit' => $weeklyLimit, 'proposed_by_id' => $author?->id, 'status' => AgreementProposalStatus::Proposed])->save();
        $agreement->forceFill(['current_version' => $version])->save();
        Log::debug('agreement.proposal_created', ['agreement_id' => $agreement->id, 'proposal_id' => $proposal->id, 'version' => $version]);

        return $proposal;
    }
}
