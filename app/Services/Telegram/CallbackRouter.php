<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Data\Telegram\IncomingUpdate;
use App\Enums\TelegramActionStage;
use App\Enums\TelegramOperation;
use App\Models\Couple;
use App\Models\MoodEntry;
use App\Models\TelegramAccount;
use App\Models\TelegramCallbackAction;
use App\Models\User;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class CallbackRouter
{
    public function __construct(private readonly RequestContext $context, private readonly CallbackOperations $operations, private readonly KeyboardFactory $keyboards) {}

    /** @return array<string, mixed> */
    public function execute(IncomingUpdate $update): array
    {
        if (preg_match('/^(act|cancel):([A-Za-z0-9]{40})$/D', $update->callbackData ?? '', $matches) !== 1) {
            $this->deny('invalid_token');
        }
        $action = TelegramCallbackAction::query()->where('token_hash', hash('sha256', $matches[2]))->first();
        $account = TelegramAccount::query()->where('telegram_id', $update->telegramId)->where('chat_id', $update->telegramId)->first();
        if ($action === null || $account === null || $account->id !== $action->telegram_account_id) {
            $this->deny('foreign_identity');
        }
        $actor = User::query()->findOrFail($account->user_id);
        $couple = $this->context->lockCouple($actor, Couple::query()->findOrFail($action->couple_id));
        $currentAccount = TelegramAccount::query()->whereKey($account->id)->lockForUpdate()->first();
        if ($currentAccount === null || $currentAccount->user_id !== $actor->id || $currentAccount->telegram_id !== $update->telegramId || $currentAccount->chat_id !== $update->telegramId) {
            $this->deny('account_changed');
        }
        $action = TelegramCallbackAction::query()->whereKey($action->id)->lockForUpdate()->firstOrFail();
        if ($action->expires_at->lessThanOrEqualTo(now())) {
            $this->deny('expired');
        }
        if (in_array($action->stage, [TelegramActionStage::Consumed, TelegramActionStage::Cancelled, TelegramActionStage::Awaiting], true)) {
            return ['text' => __('Эта кнопка уже использована. Проверьте результат в приложении.')];
        }
        if ($matches[1] === 'cancel') {
            if ($action->stage !== TelegramActionStage::Confirm) {
                $this->deny('not_confirming');
            }
            $action->forceFill(['stage' => TelegramActionStage::Cancelled])->save();

            return ['text' => __('Действие отменено.')];
        }
        $this->operations->requireCurrent($action, $actor, $couple);
        if (! in_array($action->stage, [TelegramActionStage::Ready, TelegramActionStage::Confirm], true)) {
            $this->deny('invalid_stage');
        }
        $this->operations->execute($action, $actor, $couple);
        $action->forceFill(['stage' => TelegramActionStage::Consumed])->save();
        Log::info('telegram.action_completed', ['action_id' => $action->id, 'actor_id' => $actor->id, 'operation' => $action->operation->value]);

        if ($action->source_kind === 'mood') {
            $entry = MoodEntry::query()->where('couple_id', $couple->id)->where('user_id', $actor->id)->where('operation_key', $action->operation_key)->sole();

            return ['text' => __('Настроение сохранено. Ответьте одним эмоджи или стикером, который подробнее описывает ваше настроение. Чтобы пропустить, отправьте /skip.'), 'reply_markup' => $this->keyboards->createMoodEmoji($entry, $currentAccount)];
        }
        if ($action->source_kind === 'mood_emoji') {
            return ['text' => $action->operation === TelegramOperation::MoodEmojiSkip ? __('Настроение сохранено без эмоджи.') : __('Эмоджи сохранён и виден партнёру.')];
        }

        return ['text' => __('Готово. Результат сохранён в приложении.')];
    }

    private function deny(string $reason): never
    {
        Log::warning('telegram.action_denied', ['reason' => $reason]);
        throw ValidationException::withMessages(['telegram' => __('Кнопка недоступна. Откройте актуальную страницу приложения.')]);
    }
}
