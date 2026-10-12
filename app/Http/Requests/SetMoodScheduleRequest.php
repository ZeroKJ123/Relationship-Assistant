<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\MoodPrompt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SetMoodScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'], 'expected_version' => ['required', 'integer', 'min:0'], 'confirmation' => ['required', 'accepted'],
            'times' => ['sometimes', 'array', 'list', sprintf('max:%d', MoodPrompt::MAXIMUM_TIMES + 1)],
            'times.*' => ['nullable', 'string', sprintf('date_format:%s', MoodPrompt::TIME_FORMAT)],
        ];
    }

    /** @return array<int,\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->has('times')) {
                return;
            }
            $count = count($this->getTimes() ?? []);
            if ($count === 0) {
                $validator->errors()->add('times', __('Укажите хотя бы одно время или отключите расписание.'));
            }
            if ($count > MoodPrompt::MAXIMUM_TIMES) {
                $validator->errors()->add('times', __('Можно выбрать не больше :count вариантов времени.', ['count' => MoodPrompt::MAXIMUM_TIMES]));
            }
        }];
    }

    /** @return ?list<string> выбранное время без пустых строк и повторов по возрастанию; null — время не передано */
    public function getTimes(): ?array
    {
        if (! $this->has('times')) {
            return null;
        }
        $times = array_values(array_unique(array_filter($this->array('times'), fn (mixed $time): bool => is_string($time) && $time !== '')));
        sort($times);

        return $times;
    }
}
