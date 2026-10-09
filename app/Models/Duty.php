<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DutyKind;
use App\Enums\DutyStatus;
use Carbon\CarbonImmutable;
use Database\Factories\DutyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id
 * @property int $partner_request_id
 * @property int $couple_id
 * @property ?int $request_condition_id
 * @property int $executor_id
 * @property int $reviewer_id
 * @property DutyKind $kind
 * @property string $description
 * @property CarbonImmutable $due_at
 * @property string $timezone
 * @property DutyStatus $status
 * @property int $version
 * @property bool $is_reward_eligible
 * @property int $tariff_version
 * @property int $reward
 * @property int $overdue_penalty
 * @property int $false_report_penalty
 * @property ?CarbonImmutable $reported_at
 * @property ?CarbonImmutable $reviewed_at
 * @property ?CarbonImmutable $overdue_at
 * @property Couple $couple
 * @property User $executor
 * @property User $reviewer
 */
final class Duty extends Model
{
    /** @use HasFactory<DutyFactory> */
    use HasFactory;

    /** @return BelongsTo<Couple,$this> */
    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    /** @return BelongsTo<User,$this> */
    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executor_id');
    }

    /** @return BelongsTo<User,$this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return HasMany<DutyConfirmation,$this> */
    public function confirmations(): HasMany
    {
        return $this->hasMany(DutyConfirmation::class);
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['partner_request_id' => 'integer', 'couple_id' => 'integer', 'request_condition_id' => 'integer', 'executor_id' => 'integer', 'reviewer_id' => 'integer', 'kind' => DutyKind::class, 'due_at' => 'immutable_datetime', 'status' => DutyStatus::class, 'version' => 'integer', 'is_reward_eligible' => 'boolean', 'tariff_version' => 'integer', 'reward' => 'integer', 'overdue_penalty' => 'integer', 'false_report_penalty' => 'integer', 'reported_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime', 'overdue_at' => 'immutable_datetime'];
    }
}
