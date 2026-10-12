<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SelectionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ChoreDrawFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property int $id
 * @property int $couple_id
 * @property int $first_user_id
 * @property int $second_user_id
 * @property string $operation_key
 * @property string $description
 * @property CarbonImmutable $due_at
 * @property bool $first_ready
 * @property bool $second_ready
 * @property ?int $executor_id
 * @property ?int $partner_request_id
 * @property SelectionStatus $status
 */
final class ChoreDraw extends Model
{
    /** @use HasFactory<ChoreDrawFactory> */
    use HasFactory;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['couple_id' => 'integer', 'first_user_id' => 'integer', 'second_user_id' => 'integer', 'description' => 'encrypted', 'due_at' => 'immutable_datetime', 'first_ready' => 'boolean', 'second_ready' => 'boolean', 'executor_id' => 'integer', 'partner_request_id' => 'integer', 'status' => SelectionStatus::class];
    }
}
