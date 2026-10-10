<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Dates\DateInvitationData;
use App\Enums\InvitationKind;
use App\Models\Couple;
use App\Services\Requests\InvitationTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDateInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['kind' => ['required', Rule::enum(InvitationKind::class)], 'title' => ['required', 'string', 'max:120'], 'starts_at' => ['required', 'date_format:Y-m-d\TH:i'], 'description' => ['nullable', 'string', 'max:2000'], 'dress_code' => ['nullable', 'string', 'max:500'], 'operation_key' => ['required', 'uuid'], 'request_template_id' => ['nullable', 'integer', 'min:1'], 'confirmation' => ['required', 'accepted']];
    }

    public function getData(Couple $couple, InvitationTime $time): DateInvitationData
    {
        return new DateInvitationData(kind: InvitationKind::from($this->string('kind')->toString()), title: $this->string('title')->trim()->toString(), startsAt: $time->parse($this->string('starts_at')->toString(), $couple->timezone), description: $this->filled('description') ? $this->string('description')->trim()->toString() : null, dressCode: $this->filled('dress_code') ? $this->string('dress_code')->trim()->toString() : null, operationKey: $this->string('operation_key')->toString(), templateId: $this->filled('request_template_id') ? $this->integer('request_template_id') : null);
    }
}
