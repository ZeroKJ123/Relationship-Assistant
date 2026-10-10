<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Wishlist\WishlistItemData;
use App\Enums\Currency;
use App\Services\Media\PrivatePhotos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

final class SaveWishlistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:120'], 'operation_key' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:0'], 'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', sprintf('max:%d', PrivatePhotos::MAXIMUM_KILOBYTES)], 'description' => ['nullable', 'string', 'max:2000'], 'price' => ['required', 'string', 'max:20'], 'currency' => ['required', Rule::enum(Currency::class)]];
    }

    public function getData(): WishlistItemData
    {
        $photo = $this->file('photo');

        return new WishlistItemData(title: $this->string('title')->trim()->toString(), description: $this->filled('description') ? $this->string('description')->trim()->toString() : null, price: $this->string('price')->toString(), currency: Currency::from($this->string('currency')->toString()), operationKey: $this->string('operation_key')->toString(), expectedVersion: $this->integer('expected_version'), photo: $photo instanceof UploadedFile ? $photo : null);
    }
}
