<?php

declare(strict_types=1);

namespace App\Services\Appearance;

/**
 * @phpstan-type Theme array{name:string, primary:string, ink:string, muted:string, lilac:string, butter:string, rose:string, background:string, gradient:string, angle:int, decoration:string, image:?string, icons:array<string,string>}
 * @phpstan-type Block array{id:string, type:string, enabled:bool, condition:string, tone:string, title:string, text:string}
 * @phpstan-type Tile array{id:string, enabled:bool, condition:string, tone:string, title:string}
 * @phpstan-type Layout array{columns:int, blocks:list<Block>, tiles:list<Tile>}
 * @phpstan-type Settings array{themes:array<string,Theme>, active:string, seasonal:bool, seasons:array<string,string>, icons:array<string,array{kind:string,value:string}>, layout:Layout}
 */
final class AppearanceCatalog
{
    public const ICONS = ['kitchen', 'request', 'date', 'gift', 'movies', 'car', 'rating', 'home', 'bell'];

    public const BLOCKS = ['greeting', 'summary', 'start', 'tiles', 'agreement', 'duties', 'request', 'compliment', 'text'];

    public const CONDITIONS = ['always', 'has_partner', 'no_partner', 'has_car', 'no_car', 'positive_balance', 'has_duties', 'has_request'];

    public const TONES = ['plain', 'yellow', 'purple', 'pink'];

    public const SEASONS = ['spring', 'summer', 'autumn', 'winter'];

    public const SINGLETON_ID = 1;

    public const DEFAULT_THEME = 'latte';

    /** @return Settings */
    public function defaults(): array
    {
        $base = ['name' => __('Облака и сердечки'), 'primary' => '#7035ce', 'ink' => '#26104d', 'muted' => '#675580', 'lilac' => '#ede2ff', 'butter' => '#fff1b6', 'rose' => '#ffd6e3', 'background' => '#f1eaff', 'gradient' => '#eee6ff', 'angle' => 145, 'decoration' => 'clouds', 'image' => null, 'icons' => []];
        $themes = ['classic' => $base,
            self::DEFAULT_THEME => $this->latteTheme(),
            'spring' => array_replace($base, ['name' => __('Весенний сад'), 'primary' => '#326841', 'ink' => '#183923', 'muted' => '#43694d', 'lilac' => '#d9f0dc', 'butter' => '#fff2c9', 'rose' => '#ffe0ec', 'background' => '#eff8ea', 'gradient' => '#ffe5ee', 'decoration' => 'flowers']),
            'summer' => array_replace($base, ['name' => __('Летнее солнце'), 'primary' => '#176778', 'ink' => '#163e46', 'muted' => '#4b6770', 'lilac' => '#d8f3f6', 'butter' => '#fff0bc', 'rose' => '#ffe2cc', 'background' => '#fff8db', 'gradient' => '#d5f5ff', 'decoration' => 'sun']),
            'autumn' => array_replace($base, ['name' => __('Тёплая осень'), 'primary' => '#914119', 'ink' => '#462618', 'muted' => '#755547', 'lilac' => '#f9e4d4', 'butter' => '#fff0c9', 'rose' => '#f8d9cf', 'background' => '#fff2e1', 'gradient' => '#f9dbc3', 'decoration' => 'leaves']),
            'winter' => array_replace($base, ['name' => __('Зимняя сказка'), 'primary' => '#355bad', 'ink' => '#203357', 'muted' => '#536687', 'lilac' => '#e1eaff', 'butter' => '#fff2d7', 'rose' => '#f1dfee', 'background' => '#edf5ff', 'gradient' => '#dfebff', 'decoration' => 'snow'])];
        $blocks = [];
        foreach (['greeting', 'summary', 'start', 'tiles', 'agreement', 'duties', 'request', 'compliment'] as $type) {
            $blocks[] = ['id' => $type, 'type' => $type, 'enabled' => true, 'condition' => 'always', 'tone' => 'plain', 'title' => '', 'text' => ''];
        }
        $tiles = [];
        foreach (['kitchen' => 'yellow', 'request' => 'purple', 'date' => 'pink', 'gift' => 'yellow', 'movies' => 'purple', 'car' => 'yellow', 'chores' => 'yellow', 'rating' => 'pink'] as $id => $tone) {
            $condition = match ($id) {
                'car' => 'has_car', 'chores', 'rating' => 'has_partner', default => 'always'
            };
            $tiles[] = ['id' => $id, 'enabled' => true, 'condition' => $condition, 'tone' => $tone, 'title' => ''];
        }

        return ['themes' => $themes, 'active' => self::DEFAULT_THEME, 'seasonal' => false, 'seasons' => array_combine(self::SEASONS, self::SEASONS), 'icons' => [], 'layout' => ['columns' => 2, 'blocks' => $blocks, 'tiles' => $tiles]];
    }

    /** @return Theme */
    public function latteTheme(): array
    {
        return ['name' => __('Тыквенный латте'), 'primary' => '#c4530f', 'ink' => '#3a2013', 'muted' => '#7d5a42', 'lilac' => '#f6dcb9', 'butter' => '#fbe0b0', 'rose' => '#f7d2c4', 'background' => '#fbf2e4', 'gradient' => '#f8e6cf', 'angle' => 160, 'decoration' => 'leaves', 'image' => null, 'icons' => []];
    }

    /** @return array<string,string> */
    public function blockLabels(): array
    {
        return ['greeting' => __('Приветствие и партнёр'), 'summary' => __('Настроение и баланс'), 'start' => __('Приглашение в пару'), 'tiles' => __('Быстрые действия'), 'agreement' => __('Недельный лимит'), 'duties' => __('Мои дела'), 'request' => __('Новая просьба'), 'compliment' => __('Комплимент'), 'text' => __('Текстовая карточка')];
    }

    /** @return array<string,string> */
    public function tileLabels(): array
    {
        return ['kitchen' => __('Кухня'), 'request' => __('Попросить'), 'date' => __('Свидание'), 'gift' => __('Виш-лист'), 'movies' => __('Фильмы'), 'car' => __('Мои машины'), 'chores' => __('Кто сделает?'), 'rating' => __('Оценить поступок')];
    }

    /** @return array<string,string> */
    public function iconLabels(): array
    {
        return array_replace($this->tileLabels(), ['home' => __('Главная'), 'bell' => __('Просьбы')]);
    }

    /** @return array<string,string> */
    public function conditionLabels(): array
    {
        return ['always' => __('Всегда'), 'has_partner' => __('Есть партнёр'), 'no_partner' => __('Нет партнёра'), 'has_car' => __('Машин больше нуля'), 'no_car' => __('Нет машины'), 'positive_balance' => __('Баланс больше нуля'), 'has_duties' => __('Есть дела'), 'has_request' => __('Есть новая просьба')];
    }
}
