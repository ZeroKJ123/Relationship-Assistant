<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Media\PrivatePhotos;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', sprintf('max:%d', PrivatePhotos::MAXIMUM_KILOBYTES)]];
    }
}
