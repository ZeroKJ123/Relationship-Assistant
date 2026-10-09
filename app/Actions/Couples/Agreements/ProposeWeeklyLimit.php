<?php

declare(strict_types=1);

namespace App\Actions\Couples\Agreements;

use App\Data\Couples\WeeklyLimitData;
use App\Models\AgreementProposal;
use App\Models\Couple;
use App\Models\User;
use App\Services\Couples\AgreementContext;
use App\Services\Couples\AgreementProposals;
use App\Services\Couples\WeeklyLimit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class ProposeWeeklyLimit
{
    private const PARTNER_COUNT = 2;

    public function __construct(private readonly AgreementContext $context, private readonly AgreementProposals $proposals, private readonly WeeklyLimit $limits) {}

    public function execute(User $actor, Couple $couple, WeeklyLimitData $data): AgreementProposal
    {
        Log::debug('agreement.proposal_started', ['actor_id' => $actor->id, 'couple_id' => $couple->id]);
        $this->limits->validate($data->weeklyLimit);
        $proposal = DB::transaction(function () use ($actor, $couple, $data): AgreementProposal {
            $agreement = $this->context->lockForUpdate($actor, $couple);
            $this->context->requireVersion($agreement, $data->expectedVersion);
            // Materialize locked rows to avoid a stale repeatable-read snapshot.
            $answers = $agreement->answers()->lockForUpdate()->get();
            if ($answers->count() !== self::PARTNER_COUNT) {
                throw ValidationException::withMessages(['agreement' => __('Сначала ответьте на вопрос оба, затем предложите свой вариант.')]);
            }

            return $this->proposals->create($agreement, $data->weeklyLimit, $actor);
        });
        Log::info('agreement.proposed', ['actor_id' => $actor->id, 'proposal_id' => $proposal->id, 'version' => $proposal->version]);

        return $proposal;
    }
}
