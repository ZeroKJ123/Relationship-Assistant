<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TelegramLinkCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $identity_hash
 * @property string $code_hash
 * @property array{telegram_id: string}|null $identity
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $consumed_at
 */
#[Hidden(['identity_hash', 'code_hash', 'identity'])]
final class TelegramLinkCode extends Model
{
    /** @use HasFactory<TelegramLinkCodeFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['identity' => 'encrypted:array', 'expires_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime'];
    }
}
