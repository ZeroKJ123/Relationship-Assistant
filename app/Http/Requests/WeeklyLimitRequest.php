<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Couples\WeeklyLimitData;
use App\Services\Couples\WeeklyLimit;
use Illuminate\Foundation\Http\FormRequest;

final class WeeklyLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'weekly_limit' => ['required', 'integer', sprintf('between:%d,%d', WeeklyLimit::MINIMUM, WeeklyLimit::MAXIMUM)],
            'expected_version' => ['required', 'integer', 'min:0'],
        ];
    }

    public function getData(): WeeklyLimitData
    {
        return new WeeklyLimitData($this->integer('weekly_limit'), $this->integer('expected_version'));
    }
}
