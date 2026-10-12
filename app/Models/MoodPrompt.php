<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\MoodPromptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $couple_id
 * @property Couple $couple
 * @property bool $is_self_requested
 * @property bool $is_manual
 * @property int $user_id
 * @property User $user
 * @property CarbonImmutable $scheduled_at
 * @property CarbonImmutable $expires_at
 * @property ?CarbonImmutable $answered_at
 */
final class MoodPrompt extends Model
{
    /** @use HasFactory<MoodPromptFactory> */
    use HasFactory;

    public const RESPONSE_WINDOW_HOURS = 3;

    public const DEFAULT_TIMES = ['09:00', '15:00', '18:00', '21:00'];

    public const MAXIMUM_TIMES = 8;

    public const TIME_FORMAT = 'H:i';

    /** @return BelongsTo<Couple,$this> */
    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    /** @return BelongsTo<User,$this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['is_manual' => 'boolean', 'is_self_requested' => 'boolean', 'couple_id' => 'integer', 'user_id' => 'integer', 'scheduled_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'answered_at' => 'immutable_datetime'];
    }
}
