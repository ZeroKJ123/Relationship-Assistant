<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Duties\DutyAnswerData;
use Illuminate\Foundation\Http\FormRequest;

final class ConfirmDutyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['confirmation' => ['required', 'accepted'], 'expected_version' => ['required', 'integer', 'min:0'], 'answer' => ['required', 'boolean']];
    }

    public function getData(): DutyAnswerData
    {
        return new DutyAnswerData($this->integer('expected_version'), $this->boolean('answer'));
    }
}
