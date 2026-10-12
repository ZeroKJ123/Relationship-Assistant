<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Actions\Duties\CreateDutyReminders;
use App\Enums\DeclineReason;
use App\Enums\NotificationKind;
use App\Enums\ReminderStatus;
use App\Enums\RequestType;
use App\Enums\SelectionStatus;
use App\Models\ChoreDraw;
use App\Models\Duty;
use App\Models\DutyConfirmation;
use App\Models\DutyReminderBatch;
use App\Models\MoodEntry;
use App\Models\MoodPrompt;
use App\Models\MovieChoice;
use App\Models\NotificationDelivery;
use App\Models\PartnerReminder;
use App\Models\PartnerRequest;
use App\Models\RequestEvent;
use Illuminate\Support\Str;

final class DeliveryContent
{
    private const MESSAGE_LENGTH = 3900;

    public function getText(NotificationDelivery $delivery): ?string
    {
        return match ($delivery->kind) {
            NotificationKind::RequestEvent => $this->getRequestText($delivery),
            NotificationKind::DutyEvent => $this->getDutyText($delivery),
            NotificationKind::MoodEntry => $this->getMoodText($delivery),
            NotificationKind::MoodEmoji => null,
            NotificationKind::MoodPrompt => $this->getPromptText($delivery),
            NotificationKind::Reminder => $this->getReminderText($delivery),
            NotificationKind::DutyReminder => $this->getDutyReminderText($delivery),
            NotificationKind::MovieChoice => $this->getSelectionText($delivery, MovieChoice::class),
            NotificationKind::ChoreDraw => $this->getSelectionText($delivery, ChoreDraw::class),
        };
    }

    private function getRequestText(NotificationDelivery $delivery): ?string
    {
        $event = RequestEvent::query()->find($delivery->source_id);
        if ($event === null) {
            return null;
        }
        $request = PartnerRequest::query()->whereKey($event->partner_request_id)->where('couple_id', $delivery->couple_id)->lockForUpdate()->first();
        if ($request === null || ! in_array($delivery->recipient_id, [$request->author_id, $request->recipient_id], true) || $delivery->recipient_id === $event->actor_id) {
            return null;
        }

        $label = match ($event->type) {
            'created' => $request->type === RequestType::Rating ? __('Партнёр просит оценить свой поступок:') : __('Вам просьба от партнёра:'),
            'agree' => __('Партнёр согласился на вашу просьбу:'),
            'decline' => __('Партнёр отказался от просьбы:'),
            'conditional' => __('Партнёр предлагает встречное условие:'),
            'condition_accepted' => __('Партнёр принял предложенное условие:'),
            'condition_declined' => __('Партнёр отклонил предложенное условие:'),
            'cancellation_proposed' => __('Партнёр предлагает отменить просьбу:'),
            'cancelled' => __('Просьба отменена:'),
            'rated' => __('Партнёр оценил ваш поступок:'),
            default => __($event->status->label()),
        };
        $details = [sprintf("%s\n%s", $label, Str::limit($request->description, 2000)), sprintf('%s: %s', __('Вид просьбы'), __($request->type->label())), sprintf('%s: %s', __('Ответить или выполнить до'), $request->due_at->setTimezone($request->couple->timezone)->format('d.m.Y H:i'))];
        if ($request->heart_price > 0) {
            $details[] = __('Цена: :amount сердечек', ['amount' => $request->heart_price]);
        }
        if ($request->is_very_wanted) {
            $details[] = __('Очень хочу');
        }
        if ($request->moneyRequest !== null) {
            $details[] = sprintf('%s: %s %s', __('Сумма'), $request->moneyRequest->amount, $request->moneyRequest->currency->value);
        }
        if (in_array($event->type, ['conditional', 'condition_accepted', 'condition_declined'], true)) {
            $condition = $request->conditions()->where('version', '<=', $event->version)->latest('version')->first();
            if ($condition !== null) {
                $details[] = sprintf("%s: %s\n%s: %s", __('Условие'), Str::limit($condition->description, 1000), __('Срок условия'), $condition->due_at->setTimezone($request->couple->timezone)->format('d.m.Y H:i'));
            }
        }
        if ($event->type === 'decline') {
            $details[] = $request->decline_reason === DeclineReason::NoFoodMoney ? __('Причина: нет денег на продукты.') : __('Причина: не могу согласиться.');
        }
        if ($event->type === 'rated') {
            $details[] = __('Оценка: :score из 5', ['score' => $request->rating]);
            if ($request->rating_comment !== null) {
                $details[] = Str::limit($request->rating_comment, 1000);
            }
        }
        if ($request->type === RequestType::Rating && $event->type === 'created') {
            $details[] = __('Поставьте оценку от 1 до 5 и при желании оставьте комментарий в приложении.');
        }

        return Str::limit(implode("\n", $details), self::MESSAGE_LENGTH);
    }

    private function getDutyText(NotificationDelivery $delivery): ?string
    {
        $event = DutyConfirmation::query()->find($delivery->source_id);
        if ($event === null) {
            return null;
        }
        $duty = Duty::query()->whereKey($event->duty_id)->where('couple_id', $delivery->couple_id)->lockForUpdate()->first();
        if ($duty === null || ! in_array($delivery->recipient_id, [$duty->executor_id, $duty->reviewer_id], true)) {
            return null;
        }
        $label = match ($event->stage) {
            'overdue' => __('Срок дела прошёл'),
            'report' => $event->answer === true ? __('Партнёр сообщил, что дело выполнено') : __('Партнёр сообщил, что дело не выполнено'),
            'verify' => $event->answer === true ? __('Партнёр подтвердил выполнение дела') : __('Партнёр оспорил выполнение дела'),
            default => __('Дело обновлено'),
        };

        return sprintf("%s.\n%s\n%s: %s", $label, $duty->description, __('Срок'), $duty->due_at->setTimezone($duty->timezone)->format('d.m.Y H:i'));
    }

    private function getMoodText(NotificationDelivery $delivery): ?string
    {
        $entry = MoodEntry::query()->whereKey($delivery->source_id)->where('couple_id', $delivery->couple_id)->lockForUpdate()->first();
        if ($entry === null || $entry->user_id === $delivery->recipient_id || MoodEntry::query()->where('couple_id', $entry->couple_id)->where('user_id', $entry->user_id)->where('id', '>', $entry->id)->lockForUpdate()->first() !== null) {
            return null;
        }

        return trim(sprintf('%s: %s %s', __('Настроение партнёра'), $entry->level->label(), $entry->emoji ?? ''));
    }

    private function getPromptText(NotificationDelivery $delivery): ?string
    {
        $prompt = MoodPrompt::query()->whereKey($delivery->source_id)->where('couple_id', $delivery->couple_id)->where('user_id', $delivery->recipient_id)->lockForUpdate()->first();
        if ($prompt === null || $prompt->answered_at !== null || $prompt->expires_at->lessThanOrEqualTo(now()) || $prompt->scheduled_at->isFuture()) {
            return null;
        }
        $enabled = $delivery->couple->members()->where('user_id', $delivery->recipient_id)->where('active_user_id', $delivery->recipient_id)->where('mood_schedule_enabled', true)->lockForUpdate()->first() !== null;
        $hasNewMood = MoodEntry::query()->where('couple_id', $delivery->couple_id)->where('user_id', $delivery->recipient_id)->where('recorded_at', '>=', $prompt->scheduled_at)->lockForUpdate()->first() !== null;
        if ((! $prompt->is_manual && ! $enabled) || $hasNewMood) {
            return null;
        }

        if ($prompt->is_manual) {
            return __('Партнёр хочет узнать ваше настроение. Как вы сейчас? Выберите оценку кнопкой ниже или отметьте её в приложении.');
        }

        return __('Как ваше настроение? Выберите оценку кнопкой ниже или отметьте его в приложении.');
    }

    private function getDutyReminderText(NotificationDelivery $delivery): ?string
    {
        $batch = DutyReminderBatch::query()->whereKey($delivery->source_id)->where('couple_id', $delivery->couple_id)->where('user_id', $delivery->recipient_id)->first();
        if ($batch === null || $batch->scheduled_at->isFuture()) {
            return null;
        }
        $duties = Duty::query()->where('couple_id', $batch->couple_id)->where('executor_id', $batch->user_id)->whereIn('status', CreateDutyReminders::STATUSES)->orderBy('due_at')->limit(10)->lockForUpdate()->get();
        if ($duties->isEmpty()) {
            return null;
        }

        return Str::limit(__('Ваши невыполненные дела:')."\n".$duties->map(fn (Duty $duty): string => sprintf('• %s — %s', Str::limit($duty->description, 250), $duty->due_at->setTimezone($delivery->couple->timezone)->format('d.m H:i')))->implode("\n"), self::MESSAGE_LENGTH);
    }

    /** @param class-string<MovieChoice>|class-string<ChoreDraw> $model */
    private function getSelectionText(NotificationDelivery $delivery, string $model): ?string
    {
        $selection = $model::query()->whereKey($delivery->source_id)->where('couple_id', $delivery->couple_id)->first();
        if ($selection === null || $selection->status !== SelectionStatus::Waiting || ! in_array($delivery->recipient_id, [$selection->first_user_id, $selection->second_user_id], true)) {
            return null;
        }

        return $selection instanceof ChoreDraw ? __('Партнёр предлагает жеребьёвку:')."\n".$selection->description."\n".__('Откройте приложение и подтвердите согласие на случайный выбор исполнителя.') : __('Партнёр приглашает выбрать фильм вместе. Откройте приложение: выбор начнётся после согласия обоих.');
    }

    private function getReminderText(NotificationDelivery $delivery): ?string
    {
        $reminder = PartnerReminder::query()->whereKey($delivery->source_id)->where('couple_id', $delivery->couple_id)->where('recipient_id', $delivery->recipient_id)->lockForUpdate()->first();
        if ($reminder === null || $reminder->version !== $delivery->source_version || ! in_array($reminder->status, [ReminderStatus::Ready, ReminderStatus::Sending], true) || $reminder->due_at->isFuture()) {
            return null;
        }

        return sprintf("%s\n%s", __('Напоминание от партнёра:'), $reminder->text);
    }
}
