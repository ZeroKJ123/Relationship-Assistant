<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class TelegramLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['telegram_code' => ['required', 'string', 'uuid']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['telegram_code' => __('код Telegram')];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('telegram_code'))) {
            $this->merge(['telegram_code' => strtolower(trim($this->string('telegram_code')->toString()))]);
        }
    }
}
