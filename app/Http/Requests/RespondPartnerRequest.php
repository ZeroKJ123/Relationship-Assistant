<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Requests\RequestResponseData;
use App\Enums\ConditionType;
use App\Enums\DeclineReason;
use App\Enums\RequestResponse;
use App\Models\Couple;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RespondPartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['confirmation' => ['required', 'accepted'], 'response' => ['required', Rule::enum(RequestResponse::class)], 'expected_version' => ['required', 'integer', 'min:0'],
            'reason' => ['exclude_unless:response,decline', 'required', Rule::enum(DeclineReason::class)],
            'condition_type' => ['exclude_unless:response,conditional', 'required', Rule::enum(ConditionType::class)],
            'description' => ['exclude_unless:response,conditional', 'required', 'string', 'max:2000'],
            'due_at' => ['exclude_unless:response,conditional', 'required', 'date_format:Y-m-d\TH:i']];
    }

    public function getData(Couple $couple): RequestResponseData
    {
        $response = RequestResponse::from($this->string('response')->toString());

        return new RequestResponseData(response: $response, expectedVersion: $this->integer('expected_version'), reason: $response === RequestResponse::Decline ? DeclineReason::from($this->string('reason')->toString()) : null, conditionType: $response === RequestResponse::Conditional ? ConditionType::from($this->string('condition_type')->toString()) : null, description: $response === RequestResponse::Conditional ? $this->string('description')->trim()->toString() : null, dueAt: $response === RequestResponse::Conditional ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $this->string('due_at')->toString(), $couple->timezone) : null);
    }
}
