<?php

declare(strict_types=1);

namespace App\Enums;

enum Locale: string
{
    case En = 'en';
    case Ru = 'ru';

    /** The language the current request or job is rendered in. */
    public static function current(): self
    {
        return self::tryFrom(app()->getLocale()) ?? self::En;
    }

    /** Each language is named in itself so that it stays readable in any interface language. */
    public function label(): string
    {
        return match ($this) {
            self::En => 'English',
            self::Ru => 'Русский',
        };
    }
}
