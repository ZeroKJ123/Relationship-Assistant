<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Requests\NewRequestData;
use App\Enums\RequestType;
use App\Models\Couple;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class StorePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['operation_key' => ['required', 'uuid'], 'type' => ['required', Rule::enum(RequestType::class)], 'description' => ['required', 'string', 'max:2000'], 'due_at' => ['required', 'date_format:Y-m-d\TH:i'], 'is_very_wanted' => ['prohibited']];
    }

    public function getData(Couple $couple): NewRequestData
    {
        $dueAt = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $this->string('due_at')->toString(), $couple->timezone);
        if ($dueAt === null) {
            throw ValidationException::withMessages(['due_at' => __('Укажите срок просьбы.')]);
        }

        return new NewRequestData(type: RequestType::from($this->string('type')->toString()), description: $this->string('description')->trim()->toString(), dueAt: $dueAt, operationKey: $this->string('operation_key')->toString());
    }
}
