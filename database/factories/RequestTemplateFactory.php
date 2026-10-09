<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RequestType;
use App\Models\RequestCategory;
use App\Models\RequestTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RequestTemplate> */
final class RequestTemplateFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['code' => fake()->uuid(), 'request_category_id' => RequestCategory::factory(), 'name' => fake()->sentence(), 'type' => RequestType::Service, 'sort_order' => 1];
    }
}
