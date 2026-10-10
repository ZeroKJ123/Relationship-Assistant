<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Requests\GuestVisitData;
use App\Models\Couple;
use App\Models\GuestVisit;
use App\Services\Requests\InvitationTime;
use Illuminate\Foundation\Http\FormRequest;

final class StoreGuestVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['friends' => ['required', 'array', 'list', sprintf('max:%d', GuestVisit::MAXIMUM_GUESTS)], 'friends.*' => ['required', 'string', 'distinct:strict', 'regex:/\A[1-9][0-9]*:[0-9]+\z/D'], 'starts_at' => ['required', 'date_format:Y-m-d\TH:i'], 'duration_minutes' => ['required', 'integer', sprintf('between:%d,%d', GuestVisit::MINIMUM_DURATION_MINUTES, GuestVisit::MAXIMUM_DURATION_MINUTES)], 'reason' => ['required', 'string', 'max:2000'], 'operation_key' => ['required', 'uuid'], 'request_template_id' => ['nullable', 'integer', 'min:1'], 'confirmation' => ['required', 'accepted']];
    }

    public function getData(Couple $couple, InvitationTime $time): GuestVisitData
    {
        /** @var list<string> $choices */
        $choices = $this->validated('friends');
        $friends = array_map(function (string $choice): array {
            [$id,$version] = explode(':', $choice);

            return ['id' => (int) $id, 'version' => (int) $version];
        }, $choices);

        return new GuestVisitData(friends: $friends, startsAt: $time->parse($this->string('starts_at')->toString(), $couple->timezone), durationMinutes: $this->integer('duration_minutes'), reason: $this->string('reason')->trim()->toString(), operationKey: $this->string('operation_key')->toString(), templateId: $this->filled('request_template_id') ? $this->integer('request_template_id') : null);
    }
}
