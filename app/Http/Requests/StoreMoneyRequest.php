<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Money\MoneyRequestData;
use App\Enums\Currency;
use App\Models\Couple;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class StoreMoneyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['request_template_id' => ['nullable', 'integer', 'min:1'], 'amount' => ['required', 'string', 'max:20'], 'currency' => ['required', Rule::enum(Currency::class)], 'purpose' => ['nullable', 'string', 'max:2000'], 'due_at' => ['required', 'date_format:Y-m-d\TH:i'], 'operation_key' => ['required', 'uuid'], 'expense_category_id' => ['nullable', 'integer', 'min:1'], 'confirmation' => ['required', 'accepted']];
    }

    public function getData(Couple $couple): MoneyRequestData
    {
        $dueAt = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $this->string('due_at')->toString(), $couple->timezone);
        if ($dueAt === null) {
            throw ValidationException::withMessages(['due_at' => __('Укажите срок оплаты.')]);
        }

        return new MoneyRequestData(amount: $this->string('amount')->toString(), currency: Currency::from($this->string('currency')->toString()), purpose: $this->filled('purpose') ? $this->string('purpose')->trim()->toString() : null, dueAt: $dueAt, operationKey: $this->string('operation_key')->toString(), expenseCategoryId: $this->filled('expense_category_id') ? $this->integer('expense_category_id') : null, templateId: $this->filled('request_template_id') ? $this->integer('request_template_id') : null);
    }
}
