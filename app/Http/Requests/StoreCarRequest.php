<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Catalogs\CreateCar;
use App\Data\Catalogs\CarData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:60'], 'fuel' => ['required', Rule::in(array_keys(CreateCar::FUELS))], 'operation_key' => ['required', 'uuid']];
    }

    public function getData(): CarData
    {
        return new CarData($this->string('name')->trim()->toString(), $this->string('fuel')->toString(), $this->string('operation_key')->toString());
    }
}
