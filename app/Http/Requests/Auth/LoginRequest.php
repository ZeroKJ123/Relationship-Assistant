<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('login'))) {
            $this->merge(['login' => strtolower(trim($this->string('login')->toString()))]);
        }
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return ['login' => ['required', 'string', 'max:40'], 'password' => ['required', 'string', 'max:128']];
    }
}
