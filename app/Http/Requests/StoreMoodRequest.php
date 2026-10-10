<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Mood\MoodData;
use App\Enums\MoodLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreMoodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['level' => ['required', Rule::enum(MoodLevel::class)], 'operation_key' => ['required', 'uuid'], 'prompt_id' => ['nullable', 'integer', 'min:1']];
    }

    public function getData(): MoodData
    {
        return new MoodData(MoodLevel::from($this->string('level')->toString()), $this->string('operation_key')->toString(), promptId: $this->filled('prompt_id') ? $this->integer('prompt_id') : null);
    }
}
