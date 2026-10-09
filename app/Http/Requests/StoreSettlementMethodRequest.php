<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Catalogs\SettlementMethodData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreSettlementMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function getData(): SettlementMethodData
    {
        return new SettlementMethodData($this->string('name')->trim()->toString(), $this->string('details')->trim()->toString(), $this->string('operation_key')->toString());
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'details' => ['required', 'string', 'max:500'], 'operation_key' => ['required', 'uuid']];
    }
}
