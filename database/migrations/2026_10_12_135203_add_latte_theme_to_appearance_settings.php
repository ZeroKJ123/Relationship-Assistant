<?php

declare(strict_types=1);

use App\Services\Appearance\AppearanceCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PREVIOUS_THEME = 'classic';

    private const AUTUMN_SEASON = 'autumn';

    public function up(): void
    {
        $stored = $this->settings();
        if ($stored === null) {
            return;
        }
        $settings = $stored;
        $settings['themes'][AppearanceCatalog::DEFAULT_THEME] ??= (new AppearanceCatalog)->latteTheme();
        $settings['active'] = AppearanceCatalog::DEFAULT_THEME;
        if ($settings['seasonal']) {
            $settings['seasons'][self::AUTUMN_SEASON] = AppearanceCatalog::DEFAULT_THEME;
        }
        if ($settings !== $stored) {
            $this->save($settings);
        }
    }

    public function down(): void
    {
        $settings = $this->settings();
        if ($settings === null) {
            return;
        }
        unset($settings['themes'][AppearanceCatalog::DEFAULT_THEME]);
        if ($settings['active'] === AppearanceCatalog::DEFAULT_THEME) {
            $settings['active'] = isset($settings['themes'][self::PREVIOUS_THEME]) ? self::PREVIOUS_THEME : array_key_first($settings['themes']);
        }
        if ($settings['seasons'][self::AUTUMN_SEASON] === AppearanceCatalog::DEFAULT_THEME) {
            $settings['seasons'][self::AUTUMN_SEASON] = self::AUTUMN_SEASON;
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
