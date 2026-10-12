<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Couples\MemorableDateData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class StoreMemorableDateRequest extends FormRequest
{
    private const DATE_FORMAT = 'Y-m-d';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:120'], 'occurs_on' => ['required', sprintf('date_format:%s', self::DATE_FORMAT), 'after:1900-01-01', 'before:2200-01-01'], 'is_yearly' => ['sometimes', 'boolean'], 'operation_key' => ['required', 'uuid']];
    }

    public function getData(): MemorableDateData
    {
        return new MemorableDateData(title: $this->string('title')->trim()->toString(), occursOn: CarbonImmutable::parse($this->string('occurs_on')->toString())->startOfDay(), isYearly: $this->boolean('is_yearly'), operationKey: $this->string('operation_key')->toString());
    }
}
