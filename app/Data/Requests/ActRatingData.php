<?php

declare(strict_types=1);

namespace App\Data\Requests;

final readonly class ActRatingData
{
    public function __construct(public int $expectedVersion, public int $rating, public ?string $comment) {}
}
