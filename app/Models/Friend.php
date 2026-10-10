<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\FriendFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $operation_key
 * @property string $last_operation_key
 * @property int $version
 * @property ?CarbonImmutable $archived_at
 */
final class Friend extends Model
{
    /** @use HasFactory<FriendFactory> */
    use HasFactory;

    /** @return BelongsTo<User,$this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'version' => 'integer', 'archived_at' => 'immutable_datetime'];
    }
}
