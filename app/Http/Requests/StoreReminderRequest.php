<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Reminders\ReminderData;
use App\Models\Couple;
use App\Services\Requests\InvitationTime;
use Illuminate\Foundation\Http\FormRequest;

final class StoreReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['text' => ['required', 'string', 'max:2000'], 'due_at' => ['required', 'date_format:Y-m-d\TH:i'], 'operation_key' => ['required', 'uuid'], 'confirmation' => ['required', 'accepted'], 'recipient_id' => ['prohibited']];
    }

    public function getData(Couple $couple, InvitationTime $time): ReminderData
    {
        return new ReminderData($this->string('text')->trim()->toString(), $time->parse($this->string('due_at')->toString(), $couple->timezone, 'due_at'), $this->string('operation_key')->toString());
    }
}
