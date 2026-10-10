<?php

declare(strict_types=1);

namespace App\Data\Wishlist;

use App\Enums\Currency;
use Illuminate\Http\UploadedFile;

final readonly class WishlistItemData
{
    public function __construct(public string $title, public ?string $description, public string $price, public Currency $currency, public string $operationKey, public int $expectedVersion = 0, public ?UploadedFile $photo = null) {}
}
