<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Rewards\RewardData;
use App\Services\Hearts\EconomyRules;
use Illuminate\Foundation\Http\FormRequest;

final class SaveRewardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:500'], 'heart_price' => ['required', 'integer', sprintf('between:1,%d', EconomyRules::MAXIMUM_SETTING)], 'operation_key' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:0']];
    }

    public function getData(): RewardData
    {
        $description = $this->string('description')->trim()->toString();

        return new RewardData(title: $this->string('title')->trim()->toString(), description: $description === '' ? null : $description, heartPrice: $this->integer('heart_price'), operationKey: $this->string('operation_key')->toString(), expectedVersion: $this->integer('expected_version'));
    }
}
