<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CarFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property int $id
 * @property int $user_id
 * @property int $settlement_method_id
 * @property string $operation_key
 * @property string $name
 * @property string $fuel
 */
final class Car extends Model
{
    /** @use HasFactory<CarFactory> */
    use HasFactory;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'settlement_method_id' => 'integer', 'name' => 'encrypted'];
    }
}
