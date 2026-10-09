<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Data\Auth\RegistrationData;
use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
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

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'login' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9_]+$/', Rule::unique('users', 'login')],
            'password' => ['required', 'string', 'confirmed', 'max:128', Password::min(12)->letters()->numbers()],
            'gender' => ['required', Rule::enum(Gender::class)],
        ];
    }

    public function getData(): RegistrationData
    {
        return new RegistrationData(
            name: trim($this->string('name')->toString()),
            login: $this->string('login')->toString(),
            password: $this->string('password')->toString(),
            gender: Gender::from($this->string('gender')->toString()),
        );
    }
}
