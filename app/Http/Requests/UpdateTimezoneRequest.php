<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateTimezoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['timezone' => ['required', 'string', 'timezone', 'max:64'], 'expected_timezone' => ['required', 'string', 'max:64'], 'confirmation' => ['accepted']];
    }
}
