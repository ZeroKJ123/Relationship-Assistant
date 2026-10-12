<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreComplimentsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $texts = $this->input('compliments');
        if (is_array($texts)) {
            $this->merge(['compliments' => array_values(array_filter($texts, fn (mixed $text): bool => $text !== null && (! is_string($text) || trim($text) !== '')))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['compliments' => ['nullable', 'array', 'max:50'], 'compliments.*' => ['nullable', 'string', 'max:300']];
    }
}
