<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DutyReminderBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property int $id
 * @property int $couple_id
 * @property int $user_id
 * @property CarbonImmutable $scheduled_at
 */
final class DutyReminderBatch extends Model
{
    /** @use HasFactory<DutyReminderBatchFactory> */
    use HasFactory;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['couple_id' => 'integer', 'user_id' => 'integer', 'scheduled_at' => 'immutable_datetime'];
    }
}
