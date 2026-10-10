<?php

declare(strict_types=1);

namespace App\Data\Mood;

use App\Enums\MoodLevel;
use App\Enums\MoodSource;

final readonly class MoodData
{
    public function __construct(public MoodLevel $level, public string $operationKey, public MoodSource $source = MoodSource::Web, public ?int $promptId = null) {}
}
