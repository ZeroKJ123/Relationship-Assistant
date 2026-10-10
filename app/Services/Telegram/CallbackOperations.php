<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Actions\Duties\ReportDuty;
use App\Actions\Duties\VerifyDuty;
use App\Actions\Mood\RecordMood;
use App\Actions\Requests\ResolveCondition;
use App\Actions\Requests\RespondToRequest;
use App\Data\Mood\MoodData;
use App\Data\Requests\RequestResponseData;
use App\Enums\DeclineReason;
use App\Enums\DutyStatus;
use App\Enums\MoodLevel;
use App\Enums\MoodSource;
use App\Enums\RequestResponse;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\TelegramOperation;
use App\Models\Couple;
use App\Models\Duty;
use App\Models\MoodPrompt;
use App\Models\PartnerRequest;
use App\Models\TelegramCallbackAction;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CallbackOperations
{
    public function __construct(private readonly RespondToRequest $respond, private readonly ResolveCondition $condition, private readonly ReportDuty $report, private readonly VerifyDuty $verify, private readonly RecordMood $mood) {}

    public function requireCurrent(TelegramCallbackAction $action, User $actor, Couple $couple): void
    {
        if ($action->source_kind !== $action->operation->getSourceKind()) {
            $this->deny();
        }
        if ($action->source_kind === 'request') {
            $request = PartnerRequest::query()->whereKey($action->source_id)->where('couple_id', $couple->id)->lockForUpdate()->firstOrFail();
            $isCondition = in_array($action->operation, [TelegramOperation::AcceptCondition, TelegramOperation::DeclineCondition], true);
            Gate::forUser($actor)->authorize($isCondition ? 'acceptCondition' : 'respond', $request);
            if ($request->version !== $action->source_version || $request->status !== ($isCondition ? RequestStatus::Conditional : RequestStatus::Pending) || ($action->operation === TelegramOperation::NoFoodMoney && $request->type !== RequestType::Food)) {
                $this->deny();
            }

            return;
        }
        if ($action->source_kind === 'duty') {
            $duty = Duty::query()->whereKey($action->source_id)->where('couple_id', $couple->id)->lockForUpdate()->firstOrFail();
            $isReview = in_array($action->operation, [TelegramOperation::Verify, TelegramOperation::Dispute], true);
            Gate::forUser($actor)->authorize($isReview ? 'verify' : 'report', $duty);
            $allowedStatuses = $isReview ? [DutyStatus::Reported] : [DutyStatus::Pending, DutyStatus::Overdue];
            if ($duty->version !== $action->source_version || ! in_array($duty->status, $allowedStatuses, true)) {
                $this->deny();
            }

            return;
        }
        if ($action->source_kind !== 'mood') {
            $this->deny();
        }
        $prompt = MoodPrompt::query()->whereKey($action->source_id)->where('couple_id', $couple->id)->where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
        if ($prompt->answered_at !== null || $prompt->expires_at->lessThanOrEqualTo(now()) || $prompt->scheduled_at->isFuture()) {
            $this->deny();
        }
    }

    public function execute(TelegramCallbackAction $action, User $actor, Couple $couple): void
    {
        $request = fn (): PartnerRequest => PartnerRequest::query()->whereKey($action->source_id)->where('couple_id', $couple->id)->firstOrFail();
        $duty = fn (): Duty => Duty::query()->whereKey($action->source_id)->where('couple_id', $couple->id)->firstOrFail();
        match ($action->operation) {
            TelegramOperation::Agree => $this->respond->execute($actor, $couple, $request(), new RequestResponseData(RequestResponse::Agree, $action->source_version)),
            TelegramOperation::Decline, TelegramOperation::NoFoodMoney => $this->respond->execute($actor, $couple, $request(), new RequestResponseData(RequestResponse::Decline, $action->source_version, $action->operation === TelegramOperation::NoFoodMoney ? DeclineReason::NoFoodMoney : DeclineReason::Ordinary)),
            TelegramOperation::AcceptCondition, TelegramOperation::DeclineCondition => $this->condition->execute($actor, $couple, $request(), $action->source_version, $action->operation === TelegramOperation::AcceptCondition),
            TelegramOperation::Done, TelegramOperation::NotDone => $this->report->execute($actor, $couple, $duty(), $action->source_version, $action->operation === TelegramOperation::Done),
            TelegramOperation::Verify, TelegramOperation::Dispute => $this->verify->execute($actor, $couple, $duty(), $action->source_version, $action->operation === TelegramOperation::Verify),
            default => $this->mood->execute($actor, $couple, new MoodData($this->getMood($action->operation), $action->operation_key, MoodSource::Telegram, $action->source_id)),
        };
    }

    private function getMood(TelegramOperation $operation): MoodLevel
    {
        return match ($operation) {
            TelegramOperation::MoodGreat => MoodLevel::Great,
            TelegramOperation::MoodGood => MoodLevel::Good,
            TelegramOperation::MoodNeutral => MoodLevel::Neutral,
            TelegramOperation::MoodLow => MoodLevel::Low,
            TelegramOperation::MoodBad => MoodLevel::Bad,
            default => throw ValidationException::withMessages(['telegram' => __('Действие недоступно.')]),
        };
    }

    private function deny(): never
    {
        throw ValidationException::withMessages(['telegram' => __('Состояние изменилось. Откройте актуальную страницу приложения.')]);
    }
}
