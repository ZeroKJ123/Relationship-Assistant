<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'payment_details' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
