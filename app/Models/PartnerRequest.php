<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeclineReason;
use App\Enums\DishKind;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use Carbon\CarbonImmutable;
use Database\Factories\PartnerRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property ?int $dish_id
 * @property ?int $dish_version
 * @property ?DishKind $dish_kind
 * @property ?array<string,int|string|null> $dish_snapshot
 * @property ?MoneyRequest $moneyRequest
 * @property ?int $request_template_id
 * @property ?int $expense_category_id
 * @property ?string $template_name
 * @property ?string $category_name
 * @property int $heart_price
 * @property ?int $cancellation_proposed_by_id
 * @property int $id
 * @property int $couple_id
 * @property int $author_id
 * @property int $recipient_id
 * @property RequestType $type
 * @property string $operation_key
 * @property string $description
 * @property CarbonImmutable $due_at
 * @property bool $is_very_wanted
 * @property int $refusal_tariff_version
 * @property int $refusal_tariff
 * @property ?int $decline_penalty
 * @property ?DeclineReason $decline_reason
 * @property RequestStatus $status
 * @property int $version
 * @property ?CarbonImmutable $accepted_at
 * @property Couple $couple
 * @property User $author
 * @property User $recipient
 */
final class PartnerRequest extends Model
{
    /** @use HasFactory<PartnerRequestFactory> */
    use HasFactory;

    /** @return BelongsTo<Couple, $this> */
    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /** @return HasOne<MoneyRequest, $this> */
    public function moneyRequest(): HasOne
    {
        return $this->hasOne(MoneyRequest::class);
    }

    /** @return HasMany<RequestCondition, $this> */
    public function conditions(): HasMany
    {
        return $this->hasMany(RequestCondition::class);
    }

    /** @return HasMany<RequestEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(RequestEvent::class);
    }

    /** @return HasMany<Duty, $this> */
    public function duties(): HasMany
    {
        return $this->hasMany(Duty::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['dish_id' => 'integer', 'dish_version' => 'integer', 'dish_kind' => DishKind::class, 'dish_snapshot' => 'array', 'request_template_id' => 'integer', 'expense_category_id' => 'integer', 'heart_price' => 'integer', 'cancellation_proposed_by_id' => 'integer', 'couple_id' => 'integer', 'author_id' => 'integer', 'recipient_id' => 'integer', 'type' => RequestType::class, 'due_at' => 'immutable_datetime', 'is_very_wanted' => 'boolean', 'refusal_tariff_version' => 'integer', 'refusal_tariff' => 'integer', 'decline_penalty' => 'integer', 'decline_reason' => DeclineReason::class, 'status' => RequestStatus::class, 'version' => 'integer', 'accepted_at' => 'immutable_datetime'];
    }
}
