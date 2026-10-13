<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\Enums\Locale;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Cookie;

final class LocalePreference
{
    public const COOKIE = 'locale';

    private const COOKIE_MINUTES = 60 * 24 * 365;

    /** A signed-in user's choice wins; the cookie keeps the language on guest pages of the same device. */
    public function resolve(?User $user, mixed $cookie): Locale
    {
        if ($user?->locale !== null) {
            return $user->locale;
        }

        return (is_string($cookie) ? Locale::tryFrom($cookie) : null) ?? $this->getDefault();
    }

    public function remember(Locale $locale): void
    {
        Cookie::queue(self::COOKIE, $locale->value, self::COOKIE_MINUTES);
    }

    /**
     * Renders texts for a person outside their own request: a queued notification or a bot reply.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function runFor(?User $user, Closure $callback): mixed
    {
        $previous = app()->getLocale();
        app()->setLocale($this->resolve($user, null)->value);
        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
        }
    }

    private function getDefault(): Locale
    {
        $configured = config('app.locale');

        return (is_string($configured) ? Locale::tryFrom($configured) : null) ?? Locale::En;
    }
}
