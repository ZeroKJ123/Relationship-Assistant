<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DutyConfirmationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property int $id
 * @property int $duty_id
 * @property ?int $actor_id
 * @property string $stage
 * @property ?bool $answer
 * @property int $version
 */
final class DutyConfirmation extends Model
{
    /** @use HasFactory<DutyConfirmationFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['duty_id' => 'integer', 'actor_id' => 'integer', 'answer' => 'boolean', 'version' => 'integer', 'created_at' => 'immutable_datetime'];
    }
}
