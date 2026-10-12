<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Data\Admin\AppearanceChangeData;
use App\Models\AdminAudit;
use App\Models\AppearanceSetting;
use App\Models\User;
use App\Services\Admin\PanelAccess;
use App\Services\Appearance\AppearanceCatalog;
use App\Services\Appearance\AppearanceStore;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * @phpstan-import-type Settings from AppearanceCatalog
 * @phpstan-import-type Theme from AppearanceCatalog
 * @phpstan-import-type Layout from AppearanceCatalog
 */
final class UpdateAppearance
{
    private const MAX_THEMES = 30;

    public function __construct(private readonly PanelAccess $access, private readonly AppearanceStore $appearance) {}

    public function execute(User $actor, AppearanceChangeData $data, Request $request): void
    {
        abort_unless($this->access->isUnlocked($request, $actor), Response::HTTP_FORBIDDEN);
        $asset = null;
        $image = $data->image;
        if ($image !== null) {
            $asset = $image->hashName();
            Storage::disk('appearance_assets')->putFileAs('', $image, $asset);
        }
        DB::transaction(function () use ($actor, $data, $asset): void {
            $record = AppearanceSetting::query()->whereKey(AppearanceCatalog::SINGLETON_ID)->lockForUpdate()->firstOrFail();
            if ($record->revision !== $data->revision) {
                throw ValidationException::withMessages(['revision' => __('Настройки уже изменены в другой вкладке. Обновите страницу перед сохранением.')]);
            }
            $settings = $record->settings;
            $section = $data->section;
            $settings = match ($section) {
                'theme' => $this->saveTheme($settings, $data, $asset), 'selection' => $this->selectTheme($settings, $data), 'icons' => $this->saveIcon($settings, $data, $asset), 'layout' => $this->saveLayout($settings, $data), default => $settings
            };
            $record->forceFill(['settings' => $settings, 'revision' => $record->revision + 1])->save();
            $audit = new AdminAudit;
            $audit->forceFill(['admin_id' => $actor->id, 'operation' => sprintf('appearance.%s', $section)])->save();
            DB::afterCommit(fn () => Log::info('appearance.updated', ['admin_id' => $actor->id, 'section' => $section, 'revision' => $record->revision]));
        }, attempts: 3);
        $this->appearance->invalidate();
    }

    /**
     * @param  Settings  $settings
     * @return Settings
     */
    private function saveTheme(array $settings, AppearanceChangeData $data, ?string $asset): array
    {
        $id = $data->themeId;
        if (! isset($settings['themes'][$id]) && count($settings['themes']) >= self::MAX_THEMES) {
            throw ValidationException::withMessages(['theme_id' => __('Достигнут лимит 30 тем. Измените одну из существующих.')]);
        }
        $theme = $data->theme ?? throw new LogicException('Theme data is required.');
        $theme['image'] = $asset ?? ($data->isImageRemoved ? null : ($settings['themes'][$id]['image'] ?? null));
        $settings['themes'][$id] = $theme;

        return $settings;
    }

    /**
     * @param  Settings  $settings
     * @return Settings
     */
    private function selectTheme(array $settings, AppearanceChangeData $data): array
    {
        $active = $data->active;
        $seasons = $data->seasons;
        foreach ([$active, ...array_values($seasons)] as $id) {
            if (! isset($settings['themes'][$id])) {
                throw ValidationException::withMessages(['active' => __('Выберите существующую тему для каждого сезона.')]);
            }
        }
        $settings['active'] = $active;
        $settings['seasonal'] = $data->isSeasonal;
        $settings['seasons'] = $seasons;

        return $settings;
    }

    /**
     * @param  Settings  $settings
     * @return Settings
     */
    private function saveIcon(array $settings, AppearanceChangeData $data, ?string $asset): array
    {
        $icon = $data->icon;
        $kind = $data->kind;
        if ($kind === 'default') {
            unset($settings['icons'][$icon]);

            return $settings;
        }
        $settings['icons'][$icon] = ['kind' => $kind, 'value' => $kind === 'image' ? (string) $asset : $data->glyph];

        return $settings;
    }

    /**
     * @param  Settings  $settings
     * @return Settings
     */
    private function saveLayout(array $settings, AppearanceChangeData $data): array
    {
        $layout = $data->layout ?? throw new LogicException('Layout data is required.');
        $settings['layout'] = $layout;

        return $settings;
    }
}
