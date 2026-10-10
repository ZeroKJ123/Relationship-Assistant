<?php

declare(strict_types=1);

namespace App\Data\Catalogs;

final readonly class MovieData
{
    public function __construct(public string $title, public ?int $year, public ?string $description, public bool $isFavorite) {}
}
