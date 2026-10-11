<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MoodLevel;
use App\Enums\MoodSource;
use App\Enums\MoodStickerFormat;
use App\Enums\MoodStickerStatus;
use Carbon\CarbonImmutable;
use Database\Factories\MoodEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ?string $sticker_path
 * @property ?MoodStickerFormat $sticker_format
 * @property ?MoodStickerStatus $sticker_status
 * @property ?string $emoji
 * @property int $id
 * @property int $couple_id
 * @property Couple $couple
 * @property int $user_id
 * @property User $user
 * @property MoodLevel $level
 * @property MoodSource $source
 * @property string $operation_key
 * @property ?int $mood_prompt_id
 * @property CarbonImmutable $recorded_at
 */
final class MoodEntry extends Model
{
    /** @use HasFactory<MoodEntryFactory> */
    use HasFactory;

    public const HISTORY_DAYS = 30;

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
        return ['sticker_format' => MoodStickerFormat::class, 'sticker_status' => MoodStickerStatus::class, 'couple_id' => 'integer', 'user_id' => 'integer', 'level' => MoodLevel::class, 'source' => MoodSource::class, 'mood_prompt_id' => 'integer', 'recorded_at' => 'immutable_datetime'];
    }
}
