<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Enums\ConditionType;
use App\Enums\DutyStatus;
use App\Enums\MoodLevel;
use App\Enums\NotificationKind;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\TelegramActionStage;
use App\Enums\TelegramOperation;
use App\Models\Duty;
use App\Models\DutyConfirmation;
use App\Models\MoodPrompt;
use App\Models\NotificationDelivery;
use App\Models\PartnerRequest;
use App\Models\RequestEvent;
use App\Models\TelegramAccount;
use App\Models\TelegramCallbackAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class KeyboardFactory
{
    public const TOKEN_LENGTH = 40;

    private const LIFETIME_HOURS = 24;

    /** @return array{inline_keyboard: list<list<array{text: string, callback_data: string}|array{text: string, url: string}>>} */
    public function create(NotificationDelivery $delivery, TelegramAccount $account): array
    {
        $rows = match ($delivery->kind) {
            NotificationKind::RequestEvent => $this->getRequestButtons($delivery, $account),
            NotificationKind::DutyEvent => $this->getDutyButtons($delivery, $account),
            NotificationKind::MoodPrompt => $this->getMoodButtons($delivery, $account),
            default => [],
        };

        return ['inline_keyboard' => $rows];
    }

    /** @return array{text: string, callback_data: string} */
    public function createButton(TelegramAccount $account, int $coupleId, string $sourceKind, int $sourceId, int $sourceVersion, TelegramOperation $operation, ?string $label = null): array
    {
        if (DB::transactionLevel() === 0 || $sourceKind !== $operation->getSourceKind()) {
            throw new LogicException('Callback buttons require a transaction and a matching source.');
        }
        $token = Str::random(self::TOKEN_LENGTH);
        (new TelegramCallbackAction)->forceFill(['telegram_account_id' => $account->id, 'couple_id' => $coupleId, 'token_hash' => hash('sha256', $token), 'source_kind' => $sourceKind, 'source_id' => $sourceId, 'source_version' => $sourceVersion, 'operation' => $operation, 'operation_key' => Str::uuid()->toString(), 'stage' => TelegramActionStage::Ready, 'expires_at' => now()->addHours(self::LIFETIME_HOURS)])->save();

        return ['text' => $label ?? $operation->label(), 'callback_data' => sprintf('act:%s', $token)];
    }

    /** @return list<list<array{text: string, callback_data: string}|array{text: string, url: string}>> */
    private function getRequestButtons(NotificationDelivery $delivery, TelegramAccount $account): array
    {
        $event = RequestEvent::query()->findOrFail($delivery->source_id);
        $request = PartnerRequest::query()->whereKey($event->partner_request_id)->where('couple_id', $delivery->couple_id)->lockForUpdate()->firstOrFail();
        $operations = [];
        if ($request->status === RequestStatus::Pending && $account->user_id === $request->recipient_id) {
            $operations = [TelegramOperation::Agree, TelegramOperation::Decline];
            if ($request->type === RequestType::Food) {
                $operations[] = TelegramOperation::NoFoodMoney;
            }
        }
        if ($request->status === RequestStatus::Conditional && $account->user_id === $request->author_id) {
            $operations = [TelegramOperation::AcceptCondition, TelegramOperation::DeclineCondition];
        }
        $rows = array_map(fn (TelegramOperation $operation): array => [$this->createButton($account, $delivery->couple_id, 'request', $request->id, $request->version, $operation)], $operations);
        if ($request->status === RequestStatus::Pending && $account->user_id === $request->recipient_id && $request->type !== RequestType::Money) {
            $rows[] = [['text' => __('Предложить условие в приложении'), 'url' => route('requests.show', [$request->couple_id, $request->id])]];
        }
        foreach ($request->duties()->with(['partnerRequest', 'requestCondition'])->lockForUpdate()->get() as $duty) {
            $rows = [...$rows, ...$this->getDutyRows($duty, $account)];
        }

        return $rows;
    }

    /** @return list<list<array{text: string, callback_data: string}|array{text: string, url: string}>> */
    private function getDutyButtons(NotificationDelivery $delivery, TelegramAccount $account): array
    {
        $event = DutyConfirmation::query()->findOrFail($delivery->source_id);
        $duty = Duty::query()->with(['partnerRequest', 'requestCondition'])->whereKey($event->duty_id)->where('couple_id', $delivery->couple_id)->lockForUpdate()->firstOrFail();

        return $this->getDutyRows($duty, $account);
    }

    /** @return list<list<array{text: string, callback_data: string}|array{text: string, url: string}>> */
    private function getDutyRows(Duty $duty, TelegramAccount $account): array
    {
        $operations = [];
        if (in_array($duty->status, [DutyStatus::Pending, DutyStatus::Overdue], true) && $duty->executor_id === $account->user_id) {
            $operations = [TelegramOperation::Done, TelegramOperation::NotDone];
        }
        if ($duty->status === DutyStatus::Reported && $duty->reviewer_id === $account->user_id) {
            $operations = [TelegramOperation::Verify, TelegramOperation::Dispute];
        }

        $isPayment = $duty->request_condition_id === null
            ? $duty->partnerRequest->type === RequestType::Money
            : $duty->requestCondition?->type === ConditionType::Money;

        return array_map(fn (TelegramOperation $operation): array => [$this->createButton($account, $duty->couple_id, 'duty', $duty->id, $duty->version, $operation, sprintf('%s: %s', $duty->kind->label(), $isPayment && $operation === TelegramOperation::Done ? __('Оплачено') : $operation->label()))], $operations);
    }

    /** @return list<list<array{text: string, callback_data: string}|array{text: string, url: string}>> */
    private function getMoodButtons(NotificationDelivery $delivery, TelegramAccount $account): array
    {
        $prompt = MoodPrompt::query()->whereKey($delivery->source_id)->where('couple_id', $delivery->couple_id)->where('user_id', $account->user_id)->lockForUpdate()->firstOrFail();

        return array_map(fn (MoodLevel $level): array => [$this->createButton($account, $delivery->couple_id, 'mood', $prompt->id, 1, TelegramOperation::from(sprintf('mood_%s', $level->value)))], MoodLevel::cases());
    }
}
