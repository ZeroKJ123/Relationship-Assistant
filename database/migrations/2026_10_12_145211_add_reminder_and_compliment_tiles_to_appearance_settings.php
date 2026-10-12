<?php

declare(strict_types=1);

use App\Services\Appearance\AppearanceCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TILES = ['reminder', 'compliments'];

    public function up(): void
    {
        $stored = $this->settings();
        if ($stored === null) {
            return;
        }
        $settings = $stored;
        $present = array_column($settings['layout']['tiles'], 'id');
        $catalog = new AppearanceCatalog;
        foreach (array_diff(self::TILES, $present) as $id) {
            $settings['layout']['tiles'][] = $catalog->tile($id);
        }
        if ($settings !== $stored) {
            $this->save($settings);
        }
    }

    /** Откат убирает добавленные быстрые действия вместе с их подписью и цветом. */
    public function down(): void
    {
        $settings = $this->settings();
        if ($settings === null) {
            return;
        }
        $settings['layout']['tiles'] = array_values(array_filter($settings['layout']['tiles'], fn (array $tile): bool => ! in_array($tile['id'], self::TILES, true)));
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
