<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Friends\FriendData;
use Illuminate\Foundation\Http\FormRequest;

final class SaveFriendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120'], 'operation_key' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:0']];
    }

    public function getData(): FriendData
    {
        return new FriendData(name: $this->string('name')->trim()->toString(), operationKey: $this->string('operation_key')->toString(), expectedVersion: $this->integer('expected_version'));
    }
}
