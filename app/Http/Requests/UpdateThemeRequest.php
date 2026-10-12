<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Appearance\AppearanceStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        return ['theme' => ['required', 'string', Rule::in(array_keys(app(AppearanceStore::class)->selectableThemes()))]];
    }
}
