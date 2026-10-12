<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AdminAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdminAudit> */
final class AdminAuditFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['admin_id' => User::factory(), 'target_user_id' => null, 'operation' => 'test', 'response_status' => null];
    }
}
