<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    private const COUPLE_ACTIONS_PER_MINUTE = 20;

    private const LOGIN_REQUESTS_PER_MINUTE = 20;

    private const REGISTRATIONS_PER_HOUR = 5;

    private const RECOVERY_ATTEMPTS_PER_MINUTE = 5;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-system-settings', fn (User $user): bool => $user->is_admin);
        RateLimiter::for('couple-actions', fn (Request $request): Limit => Limit::perMinute(self::COUPLE_ACTIONS_PER_MINUTE)->by($request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'unknown'));
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(self::LOGIN_REQUESTS_PER_MINUTE)->by($request->ip() ?? 'unknown'));
        RateLimiter::for('registration', fn (Request $request): Limit => Limit::perHour(self::REGISTRATIONS_PER_HOUR)->by($request->ip() ?? 'unknown'));
        RateLimiter::for('recovery', fn (Request $request): array => $this->createRecoveryLimits($request));
    }

    /** @return list<Limit> */
    private function createRecoveryLimits(Request $request): array
    {
        $login = $request->input('login');
        $normalizedLogin = is_string($login) ? strtolower(trim($login)) : 'invalid';

        return [
            Limit::perMinute(self::RECOVERY_ATTEMPTS_PER_MINUTE)->by($request->ip() ?? 'unknown'),
            Limit::perMinute(self::RECOVERY_ATTEMPTS_PER_MINUTE)->by(hash('sha256', $normalizedLogin)),
        ];
    }
}
