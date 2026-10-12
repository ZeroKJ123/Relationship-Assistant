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
use App\Models\MemorableDateNotice;
use App\Models\MoodEntry;
use App\Models\MoodPrompt;
use App\Models\MovieChoice;
use App\Models\NotificationDelivery;
use App\Models\PartnerReminder;
use App\Models\PartnerRequest;
use App\Models\RequestEvent;
use App\Models\User;
use App\Services\Couples\MemorableDates;
use Carbon\CarbonImmutable;

final class DeliveryContent
{
    private const DESCRIPTION_LENGTH = 1500;

    private const DETAIL_LENGTH = 800;

    private const DUTY_TITLE_LENGTH = 250;

    private const REMINDER_DUTIES = 10;

    public function __construct(private readonly MessageText $message) {}

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
            NotificationKind::MemorableDate => $this->getMemorableDateText($delivery),
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
        $actor = User::query()->findOrFail($event->actor_id);
        $timezone = $request->couple->timezone;
        $lines = [$this->getRequestTitle($event, $request, $actor)];
        $condition = in_array($event->type, ['conditional', 'condition_accepted', 'condition_declined'], true) ? $request->conditions()->where('version', '<=', $event->version)->latest('version')->first() : null;
        if ($condition === null) {
            $lines[] = $this->message->text($request->description, self::DESCRIPTION_LENGTH);
        }
        if ($condition !== null) {
            $lines[] = $this->message->labeled($request->author_id === $delivery->recipient_id ? __('Ваша просьба') : __('Просьба'), $request->description, self::DESCRIPTION_LENGTH);
            $lines[] = $this->message->labeled(__('Условие'), $condition->description, self::DETAIL_LENGTH);
        }
        if ($event->type === 'rated' && $request->rating_comment !== null) {
            $lines[] = $this->message->labeled(__('Комментарий'), $request->rating_comment, self::DETAIL_LENGTH);
        }
        if ($event->type === 'decline') {
            $lines[] = $request->decline_reason === DeclineReason::NoFoodMoney ? __('Причина: нет денег на продукты.') : __('Причина: не могу согласиться.');
        }
        $lines[] = '';
        $lines[] = $condition === null
            ? __('🕒 до :moment', ['moment' => $this->message->moment($request->due_at, $timezone)])
            : __('🕒 условие — до :moment', ['moment' => $this->message->moment($condition->due_at, $timezone)]);
        $extras = [];
        if ($request->heart_price > 0) {
            $extras[] = __('♥ :amount сердечек', ['amount' => $request->heart_price]);
        }
        if ($request->is_very_wanted) {
            $extras[] = __('⭐ очень хочу');
        }
        if ($request->moneyRequest !== null) {
            $extras[] = sprintf('💰 %s %s', $request->moneyRequest->amount, $request->moneyRequest->currency->value);
        }
        if ($extras !== []) {
            $lines[] = implode(' · ', $extras);
        }
        if ($request->type === RequestType::Rating && $event->type === 'created') {
            $lines[] = __('Поставьте оценку от 1 до 5 и при желании оставьте комментарий в приложении.');
        }

        return $this->message->compose($lines);
    }

    private function getRequestTitle(RequestEvent $event, PartnerRequest $request, User $actor): string
    {
        if ($event->type === 'created' && $request->created_by_admin_id !== null) {
            return $this->message->title('🧪', __('Системная тестовая просьба'));
        }
        $name = $actor->name;
        $said = fn (string $male, string $female, string $neutral): string => sprintf('%s %s', $name, $this->message->byGender($actor, $male, $female, $neutral));

        return match ($event->type) {
            'created' => $request->type === RequestType::Rating ? $this->message->title('⭐', __(':name просит оценить поступок', ['name' => $name])) : $this->message->title('📝', __(':name просит', ['name' => $name])),
            'agree' => $this->message->title('✅', $said(__('согласился'), __('согласилась'), __('соглашается'))),
            'decline' => $this->message->title('✖️', $said(__('отказался'), __('отказалась'), __('отказывается'))),
            'conditional' => $this->message->title('🤝', $said(__('согласен, но с условием'), __('согласна, но с условием'), __('соглашается, но с условием'))),
            'condition_accepted' => $this->message->title('✅', $said(__('принял условие'), __('приняла условие'), __('принимает условие'))),
            'condition_declined' => $this->message->title('✖️', $said(__('отклонил условие'), __('отклонила условие'), __('отклоняет условие'))),
            'cancellation_proposed' => $this->message->title('↩️', __(':name предлагает отменить просьбу', ['name' => $name])),
            'cancelled' => $this->message->title('🚫', __('Просьба отменена')),
            'rated' => $this->message->title('⭐', __(':name: оценка :score из 5', ['name' => $name, 'score' => $request->rating])),
            default => $this->message->title('📝', __($event->status->label())),
        };
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
        $executor = User::query()->findOrFail($duty->executor_id);
        $reviewer = User::query()->findOrFail($duty->reviewer_id);
        $said = fn (User $user, string $male, string $female, string $neutral): string => sprintf('%s %s', $user->name, $this->message->byGender($user, $male, $female, $neutral));
        $title = match ($event->stage) {
            'overdue' => $this->message->title('⏰', __('Срок прошёл')),
            'report' => $event->answer === true
                ? $this->message->title('🔎', $said($executor, __('отметил: сделано'), __('отметила: сделано'), __('отмечает: сделано')))
                : $this->message->title('⏳', $said($executor, __('отметил: пока не сделано'), __('отметила: пока не сделано'), __('отмечает: пока не сделано'))),
            'verify' => $event->answer === true
                ? $this->message->title('✅', $said($reviewer, __('подтвердил выполнение'), __('подтвердила выполнение'), __('подтверждает выполнение')))
                : $this->message->title('⚠️', $said($reviewer, __('оспорил выполнение'), __('оспорила выполнение'), __('оспаривает выполнение'))),
            default => $this->message->title('📝', __('Дело обновлено')),
        };
        $moment = $this->message->moment($duty->due_at, $duty->timezone);
        $lines = [$title, $this->message->text($duty->description, self::DESCRIPTION_LENGTH), $event->stage === 'overdue' ? __('было до :moment', ['moment' => $moment]) : __('🕒 срок — :moment', ['moment' => $moment])];
        if ($event->stage === 'report' && $event->answer === true && $delivery->recipient_id === $duty->reviewer_id) {
            $lines[] = '';
            $lines[] = $duty->is_reward_eligible
                ? e(__('Проверьте результат. После подтверждения :name получит до :amount ♥', ['name' => $executor->name, 'amount' => $duty->reward]))
                : __('Проверьте результат и подтвердите его.');
        }

        return $this->message->compose($lines);
    }

    private function getMoodText(NotificationDelivery $delivery): ?string
    {
        $entry = MoodEntry::query()->whereKey($delivery->source_id)->where('couple_id', $delivery->couple_id)->lockForUpdate()->first();
        if ($entry === null || $entry->user_id === $delivery->recipient_id || MoodEntry::query()->where('couple_id', $entry->couple_id)->where('user_id', $entry->user_id)->where('id', '>', $entry->id)->lockForUpdate()->first() !== null) {
            return null;
        }

        return trim(sprintf('%s %s', $this->message->title($entry->level->emoji(), sprintf('%s: %s', $entry->user->name, mb_strtolower($entry->level->label()))), e($entry->emoji ?? '')));
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
        if ($prompt->is_self_requested) {
            return $this->message->compose([$this->message->title('💬', __('Ваше настроение')), __('Выберите оценку. Партнёр её увидит; затем можно добавить эмоджи или стикер.')]);
        }
        $title = $prompt->is_manual ? __(':name спрашивает, как вы', ['name' => $this->getPartnerName($delivery)]) : __('Как ваше настроение?');

        return $this->message->compose([$this->message->title('💬', $title), __('Выберите одну оценку:')]);
    }

    private function getDutyReminderText(NotificationDelivery $delivery): ?string
    {
        $batch = DutyReminderBatch::query()->whereKey($delivery->source_id)->where('couple_id', $delivery->couple_id)->where('user_id', $delivery->recipient_id)->first();
        if ($batch === null || $batch->scheduled_at->isFuture()) {
            return null;
        }
        $duties = Duty::query()->where('couple_id', $batch->couple_id)->where('executor_id', $batch->user_id)->whereIn('status', CreateDutyReminders::STATUSES)->orderBy('due_at')->limit(self::REMINDER_DUTIES)->lockForUpdate()->get();
        if ($duties->isEmpty()) {
            return null;
        }
        $timezone = $delivery->couple->timezone;
        $now = CarbonImmutable::now($timezone);
        $titles = ['overdue' => __('⚠️ <b>Просрочено</b>'), 'today' => __('<b>Сегодня</b>'), 'later' => __('<b>Позже</b>')];
        $groups = $duties->groupBy(fn (Duty $duty): string => match (true) {
            $duty->due_at->lessThan($now) => 'overdue',
            $duty->due_at->lessThanOrEqualTo($now->endOfDay()) => 'today',
            default => 'later',
        });
        $lines = [$this->message->title('📋', __('Ваши дела: :count', ['count' => $duties->count()]))];
        foreach ($titles as $key => $title) {
            $items = $groups->get($key);
            if ($items === null) {
                continue;
            }
            $lines[] = '';
            $lines[] = $title;
            foreach ($items as $duty) {
                $lines[] = sprintf('• %s — %s', $this->message->text($duty->description, self::DUTY_TITLE_LENGTH), $key === 'today' ? $duty->due_at->setTimezone($timezone)->format('H:i') : $this->message->moment($duty->due_at, $timezone));
            }
        }

        return $this->message->compose($lines);
    }

    /** @param class-string<MovieChoice>|class-string<ChoreDraw> $model */
    private function getSelectionText(NotificationDelivery $delivery, string $model): ?string
    {
        $selection = $model::query()->whereKey($delivery->source_id)->where('couple_id', $delivery->couple_id)->first();
        if ($selection === null || $selection->status !== SelectionStatus::Waiting || ! in_array($delivery->recipient_id, [$selection->first_user_id, $selection->second_user_id], true)) {
            return null;
        }
        $name = $this->getPartnerName($delivery);
        if ($selection instanceof ChoreDraw) {
            return $this->message->compose([$this->message->title('🎲', __(':name предлагает жеребьёвку', ['name' => $name])), $this->message->text($selection->description, self::DESCRIPTION_LENGTH), '', __('Откройте приложение и подтвердите согласие на случайный выбор исполнителя.')]);
        }

        return $this->message->compose([$this->message->title('🎬', __(':name зовёт выбрать фильм', ['name' => $name])), __('Откройте приложение: выбор начнётся после согласия обоих.')]);
    }

    private function getReminderText(NotificationDelivery $delivery): ?string
    {
        $reminder = PartnerReminder::query()->whereKey($delivery->source_id)->where('couple_id', $delivery->couple_id)->where('recipient_id', $delivery->recipient_id)->lockForUpdate()->first();
        if ($reminder === null || $reminder->version !== $delivery->source_version || ! in_array($reminder->status, [ReminderStatus::Ready, ReminderStatus::Sending], true) || $reminder->due_at->isFuture()) {
            return null;
        }

        return $this->message->compose([$this->message->title('🔔', __(':name напоминает', ['name' => $this->getPartnerName($delivery)])), $this->message->text($reminder->text, self::DESCRIPTION_LENGTH)]);
    }

    private function getMemorableDateText(NotificationDelivery $delivery): ?string
    {
        $notice = MemorableDateNotice::query()->whereKey($delivery->source_id)->where('couple_id', $delivery->couple_id)->where('user_id', $delivery->recipient_id)->first();
        if ($notice === null) {
            return null;
        }
        $title = $notice->days_before === 0 ? $this->message->title('🎉', __('Сегодня: :title', ['title' => $notice->title])) : $this->message->title('🗓', __('Через :days дн.: :title', ['days' => $notice->days_before, 'title' => $notice->title]));
        $lines = [$title, sprintf('📅 %s', $notice->occurs_on->isoFormat('D MMMM'))];
        if ($notice->years !== null && in_array($notice->source_key, [MemorableDates::KEY_STARTED, MemorableDates::KEY_MARRIED], true)) {
            $lines[] = __(':count-я годовщина', ['count' => $notice->years]);
        }

        return $this->message->compose($lines);
    }

    private function getPartnerName(NotificationDelivery $delivery): string
    {
        $member = $delivery->couple->members()->whereNotNull('active_user_id')->where('user_id', '!=', $delivery->recipient_id)->with('user:id,name')->first();

        return $member?->user->name ?? __('Партнёр');
    }
}
