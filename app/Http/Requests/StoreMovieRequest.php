<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Catalogs\MovieData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreMovieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:150'], 'year' => ['nullable', 'integer', 'between:1888,2200'], 'description' => ['nullable', 'string', 'max:1000'], 'link' => ['nullable', 'url:http,https', 'max:1000'], 'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=4096,max_height=4096'], 'is_favorite' => ['sometimes', 'boolean']];
    }

    public function getData(): MovieData
    {
        return new MovieData($this->string('title')->toString(), $this->filled('year') ? $this->integer('year') : null, $this->filled('description') ? $this->string('description')->trim()->toString() : null, $this->boolean('is_favorite'), $this->filled('link') ? $this->string('link')->trim()->toString() : null);
    }
}
