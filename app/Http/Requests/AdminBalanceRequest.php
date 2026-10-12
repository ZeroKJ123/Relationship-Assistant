<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Admin\BalanceAdjustmentData;
use App\Models\User;
use App\Services\Admin\PanelAccess;
use App\Services\Hearts\EconomyRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AdminBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && app(PanelAccess::class)->isUnlocked($this, $user);
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'direction' => ['required', Rule::in(['add', 'subtract'])],
            'amount' => ['required', 'integer', 'min:1', sprintf('max:%d', EconomyRules::MAXIMUM_SETTING)],
            'reason' => ['required', 'string', 'max:500'],
            'operation_key' => ['required', 'uuid'],
            'confirmation' => ['required', 'accepted'],
        ];
    }

    public function getData(): BalanceAdjustmentData
    {
        $amount = $this->integer('amount');

        return new BalanceAdjustmentData($this->input('direction') === 'subtract' ? -$amount : $amount, $this->string('operation_key')->toString(), $this->string('reason')->toString());
    }
}
