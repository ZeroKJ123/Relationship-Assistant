<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TelegramLoginStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TelegramLoginAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property ?int $user_id
 * @property string $start_hash
 * @property string $browser_hash
 * @property ?string $confirmation_hash
 * @property TelegramLoginStatus $status
 * @property ?array{telegram_id: string, name: string} $identity
 * @property string $verification_code
 * @property CarbonImmutable $expires_at
 */
#[Hidden(['start_hash', 'browser_hash', 'confirmation_hash', 'identity', 'verification_code'])]
final class TelegramLoginAttempt extends Model
{
    /** @use HasFactory<TelegramLoginAttemptFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'status' => TelegramLoginStatus::class, 'identity' => 'encrypted:array', 'expires_at' => 'immutable_datetime'];
    }
}
