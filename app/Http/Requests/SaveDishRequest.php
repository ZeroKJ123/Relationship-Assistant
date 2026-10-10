<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Dishes\DishData;
use App\Enums\Currency;
use App\Enums\DishKind;
use App\Models\Dish;
use App\Services\Media\PrivatePhotos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

final class SaveDishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:120'], 'operation_key' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:0'], 'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', sprintf('max:%d', PrivatePhotos::MAXIMUM_KILOBYTES)], 'ingredients' => ['required', 'string', 'max:2000'], 'estimate' => ['required', 'string', 'max:20'], 'currency' => ['required', Rule::enum(Currency::class)], 'kind' => ['required', Rule::enum(DishKind::class)], 'portions' => ['required', 'integer', sprintf('between:1,%d', Dish::MAXIMUM_PORTIONS)], 'heart_price' => ['required', 'integer', sprintf('between:0,%d', Dish::MAXIMUM_HEART_PRICE)]];
    }

    public function getData(): DishData
    {
        $photo = $this->file('photo');

        return new DishData(title: $this->string('title')->trim()->toString(), ingredients: $this->string('ingredients')->trim()->toString(), estimate: $this->string('estimate')->toString(), currency: Currency::from($this->string('currency')->toString()), kind: DishKind::from($this->string('kind')->toString()), portions: $this->integer('portions'), heartPrice: $this->integer('heart_price'), operationKey: $this->string('operation_key')->toString(), expectedVersion: $this->integer('expected_version'), photo: $photo instanceof UploadedFile ? $photo : null);
    }
}
