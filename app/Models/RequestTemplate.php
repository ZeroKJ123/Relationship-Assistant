<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RequestType;
use Database\Factories\RequestTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $id
 * @property int $request_category_id
 * @property string $name
 * @property RequestType $type
 * @property RequestCategory $category
 */
final class RequestTemplate extends Model
{
    /** @use HasFactory<RequestTemplateFactory> */
    use HasFactory;

    /** @return BelongsTo<RequestCategory,$this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(RequestCategory::class, 'request_category_id');
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['request_category_id' => 'integer', 'sort_order' => 'integer', 'type' => RequestType::class];
    }
}
