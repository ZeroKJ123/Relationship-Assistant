<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AcceptRequestCondition extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['confirmation' => ['required', 'accepted'], 'expected_version' => ['required', 'integer', 'min:1'], 'decision' => ['required', 'in:accept,decline']];
    }
}
