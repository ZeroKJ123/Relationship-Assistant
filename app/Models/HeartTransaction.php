<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HeartOperationType;
use Carbon\CarbonImmutable;
use Database\Factories\HeartTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** @property int $id
 * @property int $heart_wallet_id
 * @property string $operation_key
 * @property HeartOperationType $type
 * @property int $requested_amount
 * @property int $amount
 * @property int $balance_after
 * @property ?int $actor_id
 * @property ?int $couple_id
 * @property ?int $duty_id
 * @property ?string $reason
 * @property CarbonImmutable $created_at
 */
final class HeartTransaction extends Model
{
    /** @use HasFactory<HeartTransactionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Heart history cannot be changed.');
        });
        self::deleting(function (): never {
            throw new LogicException('Heart history cannot be deleted.');
        });
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['heart_wallet_id' => 'integer', 'requested_amount' => 'integer', 'amount' => 'integer', 'balance_after' => 'integer', 'actor_id' => 'integer', 'couple_id' => 'integer', 'duty_id' => 'integer', 'type' => HeartOperationType::class, 'reason' => 'encrypted', 'created_at' => 'immutable_datetime'];
    }
}
