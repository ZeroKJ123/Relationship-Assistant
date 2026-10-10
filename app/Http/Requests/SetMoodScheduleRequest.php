<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SetMoodScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['enabled' => ['required', 'boolean'], 'expected_version' => ['required', 'integer', 'min:0'], 'confirmation' => ['required', 'accepted']];
    }
}
