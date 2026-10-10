<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\NotificationKind;
use App\Enums\ReminderStatus;
use App\Models\Duty;
use App\Models\DutyConfirmation;
use App\Models\MoodEntry;
use App\Models\MoodPrompt;
use App\Models\NotificationDelivery;
use App\Models\PartnerReminder;
use App\Models\PartnerRequest;
use App\Models\RequestEvent;

final class DeliveryContent
{
    public function getText(NotificationDelivery $delivery): ?string
    {
        return match ($delivery->kind) {
            NotificationKind::RequestEvent => $this->getRequestText($delivery),
            NotificationKind::DutyEvent => $this->getDutyText($delivery),
            NotificationKind::MoodEntry => $this->getMoodText($delivery),
            NotificationKind::MoodPrompt => $this->getPromptText($delivery),
            NotificationKind::Reminder => $this->getReminderText($delivery),
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

        return sprintf("%s: %s.\n%s", __('Просьба обновлена'), $request->status->label(), route('requests.show', [$delivery->couple_id, $request->id]));
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

        return sprintf("%s.\n%s", $label, route('duties.show', [$delivery->couple_id, $duty->id]));
    }

    private function getMoodText(NotificationDelivery $delivery): ?string
    {
        $entry = MoodEntry::query()->whereKey($delivery->source_id)->where('couple_id', $delivery->couple_id)->lockForUpdate()->first();
        if ($entry === null || $entry->user_id === $delivery->recipient_id || MoodEntry::query()->where('couple_id', $entry->couple_id)->where('user_id', $entry->user_id)->where('id', '>', $entry->id)->lockForUpdate()->first() !== null) {
            return null;
        }

        return sprintf("%s: %s.\n%s", __('Настроение партнёра'), $entry->level->label(), route('mood.index', $delivery->couple_id));
    }

    private function getPromptText(NotificationDelivery $delivery): ?string
    {
        $prompt = MoodPrompt::query()->whereKey($delivery->source_id)->where('couple_id', $delivery->couple_id)->where('user_id', $delivery->recipient_id)->lockForUpdate()->first();
        if ($prompt === null || $prompt->answered_at !== null || $prompt->expires_at->lessThanOrEqualTo(now()) || $prompt->scheduled_at->isFuture()) {
            return null;
        }
        $enabled = $delivery->couple->members()->where('user_id', $delivery->recipient_id)->where('active_user_id', $delivery->recipient_id)->where('mood_schedule_enabled', true)->lockForUpdate()->first() !== null;
        $hasNewMood = MoodEntry::query()->where('couple_id', $delivery->couple_id)->where('user_id', $delivery->recipient_id)->where('recorded_at', '>=', $prompt->scheduled_at)->lockForUpdate()->first() !== null;
        if (! $enabled || $hasNewMood) {
            return null;
        }

        return sprintf("%s\n%s", __('Как ваше настроение? Отметьте его в приложении.'), route('mood.index', $delivery->couple_id));
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
