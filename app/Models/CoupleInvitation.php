<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvitationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\CoupleInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $couple_id
 * @property int $inviter_id
 * @property ?int $accepted_by_id
 * @property string $token_hash
 * @property InvitationStatus $status
 * @property CarbonImmutable $expires_at
 * @property ?CarbonImmutable $accepted_at
 */
#[Hidden(['token_hash'])]
final class CoupleInvitation extends Model
{
    /** @use HasFactory<CoupleInvitationFactory> */
    use HasFactory;

    /** @return BelongsTo<Couple, $this> */
    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['couple_id' => 'integer', 'inviter_id' => 'integer', 'accepted_by_id' => 'integer', 'status' => InvitationStatus::class, 'expires_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime'];
    }
}
