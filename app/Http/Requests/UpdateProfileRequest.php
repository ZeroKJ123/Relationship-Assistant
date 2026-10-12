<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Profiles\UpdateProfileData;
use App\Enums\Currency;
use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'settings_only' => ['nullable', 'boolean'],
            'name' => [$this->boolean('settings_only') ? 'nullable' : 'required', 'string', 'max:80'],
            'bio' => ['nullable', 'string', 'max:500'],
            'gender' => [$this->boolean('settings_only') ? 'nullable' : 'required', Rule::enum(Gender::class)],
            'currency' => ['nullable', Rule::enum(Currency::class)],
            'payment_details' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function getData(): UpdateProfileData
    {
        $details = $this->string('payment_details')->toString();
        $bio = $this->string('bio')->trim()->toString();

        return new UpdateProfileData(
            name: $this->has('name') ? $this->string('name')->trim()->toString() : null,
            gender: $this->enum('gender', Gender::class),
            paymentDetails: $details === '' ? null : $details,
            currency: $this->enum('currency', Currency::class),
            bio: $bio === '' ? null : $bio,
            settingsOnly: $this->boolean('settings_only'),
            paymentProvided: $this->has('payment_details'),
            bioProvided: $this->has('bio'),
        );
    }
}
