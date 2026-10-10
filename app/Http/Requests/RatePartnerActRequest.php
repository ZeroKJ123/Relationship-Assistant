<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Requests\ActRatingData;
use Illuminate\Foundation\Http\FormRequest;

final class RatePartnerActRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['expected_version' => ['required', 'integer', 'min:0'], 'rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:1000'], 'confirmation' => ['accepted']];
    }

    public function getData(): ActRatingData
    {
        return new ActRatingData($this->integer('expected_version'), $this->integer('rating'), $this->filled('comment') ? $this->string('comment')->trim()->toString() : null);
    }
}
