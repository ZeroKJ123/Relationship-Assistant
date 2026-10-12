<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreChoreDrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['description' => ['required', 'string', 'max:2000'], 'due_at' => ['required', 'date_format:Y-m-d\TH:i'], 'operation_key' => ['required', 'uuid']];
    }
}
