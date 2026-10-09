<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RequestCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property int $id
 * @property string $name
 * @property string $code
 */
final class RequestCategory extends Model
{
    /** @use HasFactory<RequestCategoryFactory> */
    use HasFactory;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }
}
