<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Couples\RelationshipDatesData;
use Illuminate\Foundation\Http\FormRequest;

final class RelationshipDatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['started_on' => ['required', 'date_format:Y-m-d'], 'married_on' => ['nullable', 'date_format:Y-m-d'], 'expected_version' => ['required', 'integer', 'min:0']];
    }

    public function getData(): RelationshipDatesData
    {
        return new RelationshipDatesData($this->string('started_on')->toString(), $this->filled('married_on') ? $this->string('married_on')->toString() : null, $this->integer('expected_version'));
    }
}
