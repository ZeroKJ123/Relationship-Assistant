<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class AuthenticateAccount
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function execute(string $login, string $password, string $ip): void
    {
        $key = sprintf('login:%s', hash('sha256', "{$login}|{$ip}"));
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            Log::warning('login.rate_limited');
            throw ValidationException::withMessages(['login' => __('Слишком много попыток. Попробуйте через минуту.')]);
        }
        RateLimiter::hit($key, self::DECAY_SECONDS);
        Log::debug('login.started');
        if (! Auth::attempt(['login' => $login, 'password' => $password])) {
            Log::warning('login.failed');
            throw ValidationException::withMessages(['login' => __('Неверный логин или пароль.')]);
        }
        RateLimiter::clear($key);
        Log::info('login.completed', ['user_id' => Auth::id()]);
    }
}
