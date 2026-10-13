<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Localization\LocalePreference;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function __construct(private readonly LocalePreference $preference) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $cookie = $request->cookie(LocalePreference::COOKIE);
        $locale = $this->preference->resolve($user instanceof User ? $user : null, $cookie);
        app()->setLocale($locale->value);
        if ($user instanceof User && $cookie !== $locale->value) {
            $this->preference->remember($locale);
        }

        return $next($request);
    }
}
