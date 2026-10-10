<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TelegramActionStage;
use App\Enums\TelegramOperation;
use App\Models\Couple;
use App\Models\TelegramAccount;
use App\Models\TelegramCallbackAction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TelegramCallbackAction> */
final class TelegramCallbackActionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['telegram_account_id' => TelegramAccount::factory(), 'couple_id' => Couple::factory(), 'token_hash' => hash('sha256', Str::random(40)), 'operation' => TelegramOperation::Agree, 'source_kind' => 'request', 'source_id' => 1, 'source_version' => 1, 'operation_key' => Str::uuid()->toString(), 'stage' => TelegramActionStage::Ready, 'expires_at' => now()->addDay()];
    }
}
