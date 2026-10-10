<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Mood\AddMoodEmoji;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MoodEmojiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['emoji' => ['required', Rule::in(AddMoodEmoji::EMOJIS)]];
    }
}
