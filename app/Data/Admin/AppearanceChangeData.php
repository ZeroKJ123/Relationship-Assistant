<?php

declare(strict_types=1);

namespace App\Data\Admin;

use App\Services\Appearance\AppearanceCatalog;
use Illuminate\Http\UploadedFile;

/**
 * @phpstan-import-type Theme from AppearanceCatalog
 * @phpstan-import-type Layout from AppearanceCatalog
 */
final readonly class AppearanceChangeData
{
    /**
     * @param  ?Theme  $theme
     * @param  array<string,string>  $seasons
     * @param  ?Layout  $layout
     */
    public function __construct(
        public string $section,
        public int $revision,
        public string $themeId = '',
        public ?array $theme = null,
        public string $active = '',
        public bool $isSeasonal = false,
        public array $seasons = [],
        public string $icon = '',
        public string $kind = '',
        public string $glyph = '',
        public ?array $layout = null,
        public ?UploadedFile $image = null,
        public bool $isImageRemoved = false,
    ) {}
}
