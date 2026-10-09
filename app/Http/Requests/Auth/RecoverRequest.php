<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RecoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:40'],
            'recovery_code' => ['required', 'string', 'size:24'],
            'password' => ['required', 'string', 'confirmed', 'max:128', Password::min(12)->letters()->numbers()],
        ];
    }
}
