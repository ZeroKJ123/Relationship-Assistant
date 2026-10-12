<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MovieChoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return ['mode' => ['required', 'in:swipe,roulette'], 'operation_key' => ['required', 'uuid']];
        }
        $isAcceptance = $this->input('command') === 'answer' && $this->input('answer') === 'accept';

        return ['command' => ['required', 'in:answer,vote,spin'], 'answer' => ['required_if:command,answer', 'in:accept,decline'], 'confirmation' => $isAcceptance ? ['required', 'accepted'] : ['nullable', 'boolean'], 'movie_id' => ['required_if:command,vote', 'integer'], 'liked' => ['required_if:command,vote', 'boolean']];
    }
}
