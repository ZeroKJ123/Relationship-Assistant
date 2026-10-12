<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\StreakRestoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $couple_id
 * @property int $user_id
 * @property CarbonImmutable $restored_on
 */
final class StreakRestore extends Model
{
    /** @use HasFactory<StreakRestoreFactory> */
    use HasFactory;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['couple_id' => 'integer', 'user_id' => 'integer', 'restored_on' => 'immutable_date'];
    }
}
