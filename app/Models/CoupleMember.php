<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CoupleSlot;
use Carbon\CarbonImmutable;
use Database\Factories\CoupleMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property bool $mood_schedule_enabled
 * @property int $mood_schedule_version
 * @property int $couple_id
 * @property ?int $active_user_id
 * @property CoupleSlot $slot
 * @property CarbonImmutable $joined_at
 * @property ?CarbonImmutable $ended_at
 */
#[Hidden(['active_user_id'])]
final class CoupleMember extends Model
{
    /** @use HasFactory<CoupleMemberFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Couple, $this> */
    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['mood_schedule_enabled' => 'boolean', 'mood_schedule_version' => 'integer', 'user_id' => 'integer', 'couple_id' => 'integer', 'active_user_id' => 'integer', 'slot' => CoupleSlot::class, 'joined_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime'];
    }
}
