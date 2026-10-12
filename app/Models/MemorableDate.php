<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\MemorableDateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $couple_id
 * @property int $created_by_id
 * @property string $operation_key
 * @property string $title
 * @property CarbonImmutable $occurs_on
 * @property bool $is_yearly
 */
final class MemorableDate extends Model
{
    /** @use HasFactory<MemorableDateFactory> */
    use HasFactory;

    /** @return BelongsTo<Couple,$this> */
    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['couple_id' => 'integer', 'created_by_id' => 'integer', 'occurs_on' => 'immutable_date', 'is_yearly' => 'boolean'];
    }
}
