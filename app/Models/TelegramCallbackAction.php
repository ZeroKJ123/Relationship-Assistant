<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TelegramActionStage;
use App\Enums\TelegramOperation;
use Carbon\CarbonImmutable;
use Database\Factories\TelegramCallbackActionFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $telegram_account_id
 * @property int $couple_id
 * @property string $token_hash
 * @property TelegramOperation $operation
 * @property string $source_kind
 * @property int $source_id
 * @property int $source_version
 * @property string $operation_key
 * @property TelegramActionStage $stage
 * @property CarbonImmutable $expires_at
 */
#[Hidden(['token_hash', 'operation_key'])]
final class TelegramCallbackAction extends Model
{
    /** @use HasFactory<TelegramCallbackActionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['telegram_account_id' => 'integer', 'couple_id' => 'integer', 'operation' => TelegramOperation::class, 'stage' => TelegramActionStage::class, 'source_id' => 'integer', 'source_version' => 'integer', 'expires_at' => 'immutable_datetime'];
    }
}
