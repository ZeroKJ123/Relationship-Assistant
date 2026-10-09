<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SettlementMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property int $id
 * @property ?int $user_id
 * @property string $name
 * @property string $details
 * @property string $kind
 * @property list<string> $options
 */
final class SettlementMethod extends Model
{
    /** @use HasFactory<SettlementMethodFactory> */
    use HasFactory;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'sort_order' => 'integer', 'options' => 'array', 'name' => 'encrypted', 'details' => 'encrypted'];
    }
}
