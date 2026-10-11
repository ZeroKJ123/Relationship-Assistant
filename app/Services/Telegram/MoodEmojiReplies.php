<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Actions\Mood\AddMoodEmoji;
use App\Data\Telegram\IncomingUpdate;
use App\Enums\MoodStickerStatus;
use App\Enums\TelegramActionStage;
use App\Enums\TelegramOperation;
use App\Jobs\DownloadMoodStickerJob;
use App\Models\Couple;
use App\Models\MoodEntry;
use App\Models\TelegramAccount;
use App\Models\TelegramCallbackAction;
use App\Models\User;
use App\Rules\SingleEmoji;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

final class MoodEmojiReplies
{
    public function __construct(private readonly RequestContext $context, private readonly CallbackOperations $operations, private readonly AddMoodEmoji $addEmoji) {}

    /** @return array<string, mixed>|null */
    public function execute(IncomingUpdate $update): ?array
    {
        $account = TelegramAccount::query()->where('telegram_id', $update->telegramId)->where('chat_id', $update->telegramId)->first();
        if ($account === null || ($update->text === null && $update->sticker === null)) {
            return null;
        }
        $action = TelegramCallbackAction::query()->where('telegram_account_id', $account->id)->where('source_kind', 'mood_emoji')->where('stage', TelegramActionStage::Awaiting)->where('expires_at', '>', now())->latest('id')->first();
        if ($action === null) {
            return null;
        }
        $actor = User::query()->findOrFail($account->user_id);
        $couple = $this->context->lockCouple($actor, Couple::query()->findOrFail($action->couple_id));
        $currentAccount = TelegramAccount::query()->whereKey($account->id)->where('user_id', $actor->id)->where('telegram_id', $update->telegramId)->where('chat_id', $update->telegramId)->lockForUpdate()->firstOrFail();
        $action = TelegramCallbackAction::query()->whereKey($action->id)->where('telegram_account_id', $currentAccount->id)->lockForUpdate()->firstOrFail();
        if ($action->stage !== TelegramActionStage::Awaiting || $action->expires_at->lessThanOrEqualTo(now())) {
            return ['text' => __('Ответ уже сохранён или время ответа истекло.')];
        }
        $this->operations->requireCurrent($action, $actor, $couple);
        if ($update->sticker !== null) {
            $entry = MoodEntry::query()->whereKey($action->source_id)->where('couple_id', $couple->id)->where('user_id', $actor->id)->firstOrFail();
            $entry->forceFill(['sticker_status' => MoodStickerStatus::Pending, 'sticker_format' => $update->sticker->format])->save();
            $action->forceFill(['stage' => TelegramActionStage::Consumed])->save();
            DownloadMoodStickerJob::dispatch($entry->id, $update->sticker->fileId, $update->sticker->format);

            return ['text' => __('Стикер принят. Он появится у партнёра после загрузки.')];
        }
        $text = trim($update->text ?? '');
        $isSkip = in_array(mb_strtolower($text), ['/skip', 'пропустить'], true);
        if (! $isSkip && Validator::make(['emoji' => $text], ['emoji' => ['required', new SingleEmoji]])->fails()) {
            return ['text' => __('Ответьте одним эмоджи или стикером, который подробнее описывает настроение, или /skip, чтобы пропустить.'), 'reply_markup' => ['force_reply' => true, 'input_field_placeholder' => __('Эмоджи или стикер')]];
        }
        if ($isSkip) {
            $action->forceFill(['operation' => TelegramOperation::MoodEmojiSkip])->save();
            $this->operations->execute($action, $actor, $couple);
        }
        if (! $isSkip) {
            $entry = MoodEntry::query()->whereKey($action->source_id)->where('couple_id', $couple->id)->where('user_id', $actor->id)->firstOrFail();
            $this->addEmoji->execute($actor, $couple, $entry, $text);
        }
        $action->forceFill(['stage' => TelegramActionStage::Consumed])->save();
        Log::info('telegram.mood_emoji_reply_saved', ['action_id' => $action->id, 'actor_id' => $actor->id, 'skipped' => $isSkip]);

        return ['text' => $isSkip ? __('Настроение сохранено без эмоджи.') : __('Эмоджи сохранён и виден партнёру.')];
    }
}
