<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DateIdeaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $couple_id
 * @property int $created_by_id
 * @property string $operation_key
 * @property string $title
 */
final class DateIdea extends Model
{
    /** @use HasFactory<DateIdeaFactory> */
    use HasFactory;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['couple_id' => 'integer', 'created_by_id' => 'integer'];
    }
}
