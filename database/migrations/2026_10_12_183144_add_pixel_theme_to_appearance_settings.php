<?php

declare(strict_types=1);

use App\Services\Appearance\AppearanceCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const THEME = 'pixel';

    public function up(): void
    {
        $stored = $this->settings();
        if ($stored === null) {
            return;
        }
        $settings = $stored;
        $settings['themes'][self::THEME] ??= (new AppearanceCatalog)->personalThemes()[self::THEME];
        if ($settings !== $stored) {
            $this->save($settings);
        }
    }

    /** Откат убирает тему «Пиксельная ночь» вместе с её правками; выбравшие её участники увидят общую тему. */
    public function down(): void
    {
        $settings = $this->settings();
        if ($settings === null) {
            return;
        }
        unset($settings['themes'][self::THEME]);
        if ($settings['active'] === self::THEME) {
            $settings['active'] = AppearanceCatalog::DEFAULT_THEME;
        }
        foreach ($settings['seasons'] as $season => $themeId) {
            if ($themeId === self::THEME) {
                $settings['seasons'][$season] = AppearanceCatalog::DEFAULT_THEME;
            }
        }
        $this->save($settings);
    }

    /** @return ?array<string,mixed> */
    private function settings(): ?array
    {
        $stored = DB::table('appearance_settings')->where('id', AppearanceCatalog::SINGLETON_ID)->value('settings');

        return is_string($stored) ? json_decode($stored, true, flags: JSON_THROW_ON_ERROR) : null;
    }

    /** @param array<string,mixed> $settings */
    private function save(array $settings): void
    {
        DB::table('appearance_settings')->where('id', AppearanceCatalog::SINGLETON_ID)->update(['settings' => json_encode($settings, JSON_THROW_ON_ERROR), 'revision' => DB::raw('revision + 1'), 'updated_at' => now()]);
    }
};
