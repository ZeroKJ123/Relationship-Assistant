<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Admin\AppearanceChangeData;
use App\Models\User;
use App\Services\Admin\PanelAccess;
use App\Services\Appearance\AppearanceCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * @phpstan-import-type Theme from AppearanceCatalog
 * @phpstan-import-type Layout from AppearanceCatalog
 */
final class AdminAppearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && app(PanelAccess::class)->isUnlocked($this, $user);
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $base = ['section' => ['required', Rule::in(['theme', 'selection', 'icons', 'layout'])], 'revision' => ['required', 'integer', 'min:0'], 'confirmation' => ['required', 'accepted']];
        $specific = match ($this->input('section')) {
            'theme' => $this->themeRules(),
            'selection' => ['active' => ['required', 'string', 'max:60'], 'seasonal' => ['required', 'boolean'], 'seasons' => ['required', 'array:spring,summer,autumn,winter'], 'seasons.*' => ['required', 'string', 'max:60']],
            'icons' => ['icon' => ['required', Rule::in(AppearanceCatalog::ICONS)], 'kind' => ['required', Rule::in(['default', 'glyph', 'image'])], 'glyph' => ['required_if:kind,glyph', 'nullable', 'string', 'max:12'], 'image' => ['required_if:kind,image', 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096', 'dimensions:max_width=4096,max_height=4096']],
            'layout' => $this->layoutRules(), default => [],
        };

        return array_merge($base, $specific);
    }

    public function getData(): AppearanceChangeData
    {
        /** @var ?Theme $theme */
        $theme = $this->validated('theme');
        if ($theme !== null) {
            $theme['angle'] = $this->integer('theme.angle');
            $theme['icons'] = [];
            foreach (AppearanceCatalog::ICONS as $icon) {
                $theme['icons'][$icon] = $this->string(sprintf('theme.icons.%s', $icon))->toString();
            }
            $theme['image'] = null;
        }
        /** @var array{columns:int|string,blocks:list<array{id:string,type:string,enabled:bool|int|string,condition:string,tone:string,title:?string,text:?string}>,tiles:list<array{id:string,enabled:bool|int|string,condition:string,tone:string,title:?string}>}|null $layout */
        $layout = $this->validated('layout');
        if ($layout !== null) {
            $layout['columns'] = $this->integer('layout.columns');
            foreach ($layout['blocks'] as &$block) {
                $block['enabled'] = filter_var($block['enabled'], FILTER_VALIDATE_BOOL);
                $block['title'] = $block['title'] ?? '';
                $block['text'] = $block['text'] ?? '';
            }
            unset($block);
            foreach ($layout['tiles'] as &$tile) {
                $tile['enabled'] = filter_var($tile['enabled'], FILTER_VALIDATE_BOOL);
                $tile['title'] = $tile['title'] ?? '';
            }
            unset($tile);
        }
        $seasons = [];
        foreach (AppearanceCatalog::SEASONS as $season) {
            $seasons[$season] = $this->string(sprintf('seasons.%s', $season))->toString();
        }
        $image = $this->file('image');

        return new AppearanceChangeData(
            section: $this->string('section')->toString(),
            revision: $this->integer('revision'),
            themeId: $this->string('theme_id')->toString(),
            theme: $theme,
            active: $this->string('active')->toString(),
            isSeasonal: $this->boolean('seasonal'),
            seasons: $seasons,
            icon: $this->string('icon')->toString(),
            kind: $this->string('kind')->toString(),
            glyph: $this->string('glyph')->toString(),
            layout: $layout,
            image: $image instanceof UploadedFile ? $image : null,
            isImageRemoved: $this->boolean('remove_image'),
        );
    }

    /** @return list<callable(Validator):void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('section') !== 'layout' || $validator->errors()->isNotEmpty()) {
                return;
            }
            /** @var list<array{id:string,type:string}> $blocks */
            $blocks = $this->input('layout.blocks');
            $builtins = array_column(array_filter($blocks, fn (array $block): bool => $block['type'] !== 'text'), 'type');
            if (count($builtins) !== count(array_unique($builtins))) {
                $validator->errors()->add('layout.blocks', __('Каждый готовый блок можно добавить только один раз.'));
            }
        }];
    }

    /** @return array<string,mixed> */
    private function themeRules(): array
    {
        $rules = [
            'theme_id' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/'],
            'theme' => ['required', 'array:name,primary,ink,muted,lilac,butter,rose,background,gradient,angle,decoration,icons'],
            'theme.name' => ['required', 'string', 'max:80'],
            'theme.angle' => ['required', 'integer', 'between:0,360'],
            'theme.decoration' => ['required', Rule::in(['none', 'clouds', 'flowers', 'sun', 'leaves', 'snow'])],
            'theme.icons' => ['required', 'array:'.implode(',', AppearanceCatalog::ICONS)],
            'theme.icons.*' => ['nullable', 'string', 'max:12'],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096', 'dimensions:max_width=4096,max_height=4096'],
            'remove_image' => ['sometimes', 'boolean'],
        ];
        foreach (['primary', 'ink', 'muted', 'lilac', 'butter', 'rose', 'background', 'gradient'] as $key) {
            $rules[sprintf('theme.%s', $key)] = ['required', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'];
        }

        return $rules;
    }

    /** @return array<string,mixed> */
    private function layoutRules(): array
    {
        return [
            'layout' => ['required', 'array:columns,blocks,tiles'],
            'layout.columns' => ['required', 'integer', 'between:1,4'],
            'layout.blocks' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'layout.blocks.*' => ['required', 'array:id,type,enabled,condition,tone,title,text'],
            'layout.blocks.*.id' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/', 'distinct:strict'],
            'layout.blocks.*.type' => ['required', Rule::in(AppearanceCatalog::BLOCKS)],
            'layout.blocks.*.enabled' => ['required', 'boolean'],
            'layout.blocks.*.condition' => ['required', Rule::in(AppearanceCatalog::CONDITIONS)],
            'layout.blocks.*.tone' => ['required', Rule::in(AppearanceCatalog::TONES)],
            'layout.blocks.*.title' => ['present', 'nullable', 'string', 'max:100'],
            'layout.blocks.*.text' => ['present', 'nullable', 'string', 'max:2000'],
            'layout.tiles' => ['required', 'array', 'list', 'size:8'],
            'layout.tiles.*' => ['required', 'array:id,enabled,condition,tone,title'],
            'layout.tiles.*.id' => ['required', Rule::in(array_keys(app(AppearanceCatalog::class)->tileLabels())), 'distinct:strict'],
            'layout.tiles.*.enabled' => ['required', 'boolean'],
            'layout.tiles.*.condition' => ['required', Rule::in(AppearanceCatalog::CONDITIONS)],
            'layout.tiles.*.tone' => ['required', Rule::in(AppearanceCatalog::TONES)],
            'layout.tiles.*.title' => ['present', 'nullable', 'string', 'max:100'],
        ];
    }
}
