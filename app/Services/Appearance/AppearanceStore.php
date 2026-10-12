<?php

declare(strict_types=1);

namespace App\Services\Appearance;

use App\Models\AppearanceSetting;
use App\ViewModels\DashboardViewModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

/**
 * @phpstan-import-type Settings from AppearanceCatalog
 * @phpstan-import-type Theme from AppearanceCatalog
 * @phpstan-import-type Block from AppearanceCatalog
 * @phpstan-import-type Tile from AppearanceCatalog
 */
final class AppearanceStore
{
    private ?AppearanceSetting $snapshot = null;

    public function __construct(private readonly AppearanceCatalog $catalog) {}

    /** @return Settings */
    public function get(): array
    {
        $record = $this->record();

        return $record === null ? $this->catalog->defaults() : $record->settings;
    }

    public function revision(): int
    {
        $record = $this->record();

        return $record === null ? 0 : $record->revision;
    }

    public function invalidate(): void
    {
        $this->snapshot = null;
    }

    public function activeId(): string
    {
        $settings = $this->get();
        if (! $settings['seasonal']) {
            return $settings['active'];
        }
        $month = CarbonImmutable::now('Europe/Minsk')->month;
        $season = match (true) {
            $month >= 3 && $month <= 5 => 'spring', $month >= 6 && $month <= 8 => 'summer', $month >= 9 && $month <= 11 => 'autumn', default => 'winter'
        };

        return $settings['seasons'][$season];
    }

    /** @return Theme */
    public function theme(): array
    {
        $settings = $this->get();

        return $settings['themes'][$this->activeId()];
    }

    /** @return array{kind:string,value:string} */
    public function icon(string $name): array
    {
        $settings = $this->get();
        $global = $settings['icons'][$name] ?? null;
        if ($global !== null) {
            return $global;
        }
        $glyph = $this->theme()['icons'][$name] ?? '';

        return $glyph === '' ? ['kind' => 'default', 'value' => ''] : ['kind' => 'glyph', 'value' => $glyph];
    }

    public function css(): string
    {
        $theme = $this->theme();
        $variables = [];
        foreach (['primary' => 'violet', 'ink' => 'ink', 'muted' => 'muted', 'lilac' => 'lilac', 'butter' => 'butter', 'rose' => 'rose', 'background' => 'page-start', 'gradient' => 'page-end'] as $key => $token) {
            $variables[] = sprintf('--color-%s:%s', $token, $theme[$key]);
        }
        $variables[] = sprintf('--page-angle:%ddeg', $theme['angle']);
        $image = match (true) {
            $theme['image'] !== null => route('appearance.asset', ['asset' => $theme['image']]), $theme['decoration'] === 'clouds' => '/images/clouds-hearts-v1.png', default => null
        };
        $variables[] = $image === null ? '--page-image:none' : sprintf('--page-image:url("%s")', $image);

        return implode(';', $variables);
    }

    /** @return list<Block> */
    public function blocks(DashboardViewModel $dashboard): array
    {
        return array_values(array_filter($this->get()['layout']['blocks'], fn (array $block): bool => $block['enabled'] && $this->isVisible($block['condition'], $dashboard)));
    }

    /** @return list<Tile> */
    public function tiles(DashboardViewModel $dashboard): array
    {
        return array_values(array_filter($this->get()['layout']['tiles'], fn (array $tile): bool => $tile['enabled'] && $this->isVisible($tile['condition'], $dashboard)));
    }

    /** @return array<string,string> */
    public function tileDestinations(DashboardViewModel $dashboard): array
    {
        $coupleFallback = route('couples.index');

        return [
            'kitchen' => route('dishes.index', $dashboard->partner ? ['owner_id' => $dashboard->partner->id] : []),
            'request' => $dashboard->partner ? route('requests.create', $dashboard->couple) : $coupleFallback,
            'date' => $dashboard->partner ? route('dates.create', $dashboard->couple) : $coupleFallback,
            'gift' => route('wishlist.index'), 'movies' => route('movies.index'), 'car' => route('cars.index'),
            'chores' => $dashboard->partner ? route('chores.index', $dashboard->couple) : $coupleFallback,
            'rating' => $dashboard->partner ? route('requests.create', [$dashboard->couple, 'type' => 'rating']) : $coupleFallback,
        ];
    }

    /** @return array<string,string> */
    public function tileLabels(): array
    {
        return $this->catalog->tileLabels();
    }

    private function isVisible(string $condition, DashboardViewModel $dashboard): bool
    {
        return match ($condition) {
            'has_partner' => $dashboard->partner !== null, 'no_partner' => $dashboard->partner === null, 'has_car' => $dashboard->hasCar, 'no_car' => ! $dashboard->hasCar, 'positive_balance' => $dashboard->wallet->balance > 0, 'has_duties' => $dashboard->duties->isNotEmpty(), 'has_request' => $dashboard->newRequest !== null, default => true
        };
    }

    private function record(): ?AppearanceSetting
    {
        if ($this->snapshot !== null) {
            return $this->snapshot;
        }
        if (! Schema::hasTable('appearance_settings')) {
            return null;
        }
        $this->snapshot = AppearanceSetting::query()->find(AppearanceCatalog::SINGLETON_ID);

        return $this->snapshot;
    }
}
