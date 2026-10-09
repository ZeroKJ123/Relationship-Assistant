<?php

declare(strict_types=1);

namespace App\Actions\Couples\Agreements;

use App\Data\Couples\WeeklyLimitData;
use App\Models\AgreementAnswer;
use App\Models\Couple;
use App\Models\CoupleAgreement;
use App\Models\User;
use App\Services\Couples\AgreementContext;
use App\Services\Couples\AgreementProposals;
use App\Services\Couples\WeeklyLimit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class SubmitAnswer
{
    private const PARTNER_COUNT = 2;

    public function __construct(private readonly AgreementContext $context, private readonly AgreementProposals $proposals, private readonly WeeklyLimit $limits) {}

    public function execute(User $actor, Couple $couple, WeeklyLimitData $data): CoupleAgreement
    {
        $this->limits->validate($data->weeklyLimit);
        Log::debug('agreement.answer_started', ['actor_id' => $actor->id, 'couple_id' => $couple->id]);
        $agreement = DB::transaction(function () use ($actor, $couple, $data): CoupleAgreement {
            $agreement = $this->context->lockForUpdate($actor, $couple);
            $this->context->requireVersion($agreement, $data->expectedVersion);
            $answer = $agreement->answers()->lockForUpdate()->get()->firstWhere('user_id', $actor->id);
            if ($answer?->weekly_limit === $data->weeklyLimit) {
                return $agreement;
            }
            $answer ??= new AgreementAnswer;
            $answer->forceFill(['couple_agreement_id' => $agreement->id, 'user_id' => $actor->id, 'weekly_limit' => $data->weeklyLimit])->save();
            $answers = $agreement->answers()->orderBy('user_id')->lockForUpdate()->get();
            if ($answers->count() === self::PARTNER_COUNT) {
                $values = $answers->map(fn (AgreementAnswer $answer): int => $answer->weekly_limit)->all();
                $this->proposals->create($agreement, $this->limits->suggestCompromise($values[0], $values[1]), null);
            }

            return $agreement;
        });
        Log::info('agreement.answer_submitted', ['actor_id' => $actor->id, 'agreement_id' => $agreement->id, 'version' => $agreement->current_version]);

        return $agreement;
    }
}
