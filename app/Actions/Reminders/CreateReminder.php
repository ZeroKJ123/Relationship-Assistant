<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Data\Reminders\ReminderData;
use App\Enums\ReminderStatus;
use App\Models\Couple;
use App\Models\PartnerReminder;
use App\Models\User;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CreateReminder
{
    public function __construct(private readonly RequestContext $context) {}

    public function execute(User $actor, Couple $couple, ReminderData $data): PartnerReminder
    {
        Validator::make(['text' => $data->text, 'operation_key' => $data->operationKey], ['text' => ['required', 'string', 'max:2000'], 'operation_key' => ['required', 'uuid']])->validate();

        return DB::transaction(function () use ($actor, $couple, $data): PartnerReminder {
            $current = $this->context->lockCouple($actor, $couple);
            $existing = PartnerReminder::query()->where('author_id', $actor->id)->where('operation_key', $data->operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->couple_id !== $current->id || $existing->text !== $data->text || ! $existing->due_at->equalTo($data->dueAt->setMicrosecond(0))) {
                    throw ValidationException::withMessages(['reminder' => __('Форма уже отправлена с другими данными. Обновите страницу.')]);
                }

                return $existing;
            }
            if (! $data->dueAt->setMicrosecond(0)->isFuture()) {
                throw ValidationException::withMessages(['due_at' => __('Выберите будущее время напоминания.')]);
            }
            $recipient = $current->members()->where('user_id', '!=', $actor->id)->lockForUpdate()->firstOrFail();
            $reminder = new PartnerReminder;
            $reminder->forceFill(['couple_id' => $current->id, 'author_id' => $actor->id, 'recipient_id' => $recipient->user_id, 'text' => $data->text, 'due_at' => $data->dueAt->setMicrosecond(0)->utc(), 'operation_key' => $data->operationKey, 'version' => 0, 'status' => ReminderStatus::Scheduled])->save();
            DB::afterCommit(fn () => Log::info('reminder.created', ['reminder_id' => $reminder->id, 'actor_id' => $actor->id, 'recipient_id' => $recipient->user_id]));

            return $reminder;
        }, attempts: 3);
    }
}
