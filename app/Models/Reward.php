<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RewardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property ?string $description
 * @property int $heart_price
 * @property string $operation_key
 * @property string $last_operation_key
 * @property int $version
 * @property ?CarbonImmutable $archived_at
 */
final class Reward extends Model
{
    /** @use HasFactory<RewardFactory> */
    use HasFactory;

    /** @return BelongsTo<User,$this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'heart_price' => 'integer', 'version' => 'integer', 'archived_at' => 'immutable_datetime'];
    }
}
