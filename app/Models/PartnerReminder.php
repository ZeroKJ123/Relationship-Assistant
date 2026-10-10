<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReminderStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PartnerReminderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $couple_id
 * @property Couple $couple
 * @property int $author_id
 * @property int $recipient_id
 * @property string $text
 * @property string $operation_key
 * @property CarbonImmutable $due_at
 * @property ReminderStatus $status
 * @property int $version
 * @property ?CarbonImmutable $cancelled_at
 * @property ?CarbonImmutable $prepared_at
 * @property User $author
 * @property User $recipient
 */
final class PartnerReminder extends Model
{
    /** @use HasFactory<PartnerReminderFactory> */
    use HasFactory;

    public const MAXIMUM_DELAY_HOURS = 24;

    /** @return BelongsTo<Couple,$this> */
    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    /** @return BelongsTo<User,$this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User,$this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['couple_id' => 'integer', 'author_id' => 'integer', 'recipient_id' => 'integer', 'due_at' => 'immutable_datetime', 'status' => ReminderStatus::class, 'version' => 'integer', 'cancelled_at' => 'immutable_datetime', 'prepared_at' => 'immutable_datetime'];
    }
}
