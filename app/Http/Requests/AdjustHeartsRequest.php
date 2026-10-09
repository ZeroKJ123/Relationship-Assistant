<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Hearts\AdjustmentData;
use App\Enums\HeartOperationType;
use App\Services\Hearts\EconomyRules;
use Illuminate\Foundation\Http\FormRequest;

final class AdjustHeartsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['confirmation' => ['required', 'accepted'], 'kind' => ['required', 'in:thanks,deduction'], 'amount' => ['required', 'integer', sprintf('between:1,%d', EconomyRules::MAXIMUM_SETTING)], 'reason' => ['required', 'string', 'max:500'], 'operation_key' => ['required', 'uuid']];
    }

    public function getData(): AdjustmentData
    {
        return new AdjustmentData(type: $this->string('kind')->toString() === 'thanks' ? HeartOperationType::ManualThanks : HeartOperationType::ManualDeduction, amount: $this->integer('amount'), reason: $this->string('reason')->trim()->toString(), operationKey: $this->string('operation_key')->toString());
    }
}
