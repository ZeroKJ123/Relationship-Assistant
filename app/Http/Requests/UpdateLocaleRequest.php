<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        return ['locale' => ['required', 'string', Rule::enum(Locale::class)]];
    }

    public function getData(): Locale
    {
        return Locale::from($this->string('locale')->toString());
    }
}
