<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\MemorableDateNoticeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $couple_id
 * @property int $user_id
 * @property string $source_key
 * @property string $title
 * @property CarbonImmutable $occurs_on
 * @property int $days_before
 * @property ?int $years
 */
final class MemorableDateNotice extends Model
{
    /** @use HasFactory<MemorableDateNoticeFactory> */
    use HasFactory;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['couple_id' => 'integer', 'user_id' => 'integer', 'occurs_on' => 'immutable_date', 'days_before' => 'integer', 'years' => 'integer'];
    }
}
