<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationKind;
use Carbon\CarbonImmutable;
use Database\Factories\NotificationDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $couple_id
 * @property int $recipient_id
 * @property Couple $couple
 * @property User $recipient
 * @property NotificationKind $kind
 * @property int $source_id
 * @property ?int $source_version
 * @property string $operation_key
 * @property DeliveryStatus $status
 * @property int $attempts
 * @property CarbonImmutable $available_at
 * @property CarbonImmutable $expires_at
 * @property ?CarbonImmutable $queued_until
 * @property ?CarbonImmutable $leased_until
 * @property ?string $lease_token
 * @property ?int $telegram_account_id
 * @property ?string $chat_fingerprint
 * @property ?string $message_id
 * @property ?string $failure_reason
 * @property ?CarbonImmutable $sent_at
 */
#[Hidden(['lease_token', 'chat_fingerprint', 'message_id'])]
final class NotificationDelivery extends Model
{
    /** @use HasFactory<NotificationDeliveryFactory> */
    use HasFactory;

    public const MAXIMUM_ATTEMPTS = 5;

    public const LEASE_SECONDS = 90;

    public const QUEUE_RESERVATION_SECONDS = 300;

    public const BACKOFF_SECONDS = [30, 120, 300, 900];

    public const DEFAULT_LIFETIME_HOURS = 24;

    public const UNAVAILABLE_RETRY_SECONDS = 300;

    /** @return BelongsTo<Couple, $this> */
    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['couple_id' => 'integer', 'recipient_id' => 'integer', 'source_id' => 'integer', 'source_version' => 'integer', 'telegram_account_id' => 'integer', 'kind' => NotificationKind::class, 'status' => DeliveryStatus::class, 'attempts' => 'integer', 'available_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'queued_until' => 'immutable_datetime', 'leased_until' => 'immutable_datetime', 'sent_at' => 'immutable_datetime'];
    }
}
