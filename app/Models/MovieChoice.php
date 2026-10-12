<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SelectionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\MovieChoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property int $id
 * @property ?CarbonImmutable $spin_started_at
 * @property int $couple_id
 * @property int $first_user_id
 * @property int $second_user_id
 * @property string $operation_key
 * @property string $mode
 * @property bool $first_ready
 * @property bool $second_ready
 * @property list<int> $pool
 * @property array<int,array<int,bool>> $votes
 * @property ?int $selected_movie_id
 * @property SelectionStatus $status
 */
final class MovieChoice extends Model
{
    /** @use HasFactory<MovieChoiceFactory> */
    use HasFactory;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['spin_started_at' => 'immutable_datetime', 'couple_id' => 'integer', 'first_user_id' => 'integer', 'second_user_id' => 'integer', 'first_ready' => 'boolean', 'second_ready' => 'boolean', 'pool' => 'array', 'votes' => 'array', 'selected_movie_id' => 'integer', 'status' => SelectionStatus::class];
    }
}
