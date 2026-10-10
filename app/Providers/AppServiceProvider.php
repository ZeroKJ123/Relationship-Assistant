<?php

declare(strict_types=1);

namespace App\Providers;

use App\Jobs\SendTelegramNotificationJob;
use App\Models\User;
use App\Services\Telegram\BotClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

final class AppServiceProvider extends ServiceProvider
{
    private const TELEGRAM_MESSAGES_PER_SECOND = 20;

    private const TELEGRAM_RECIPIENT_MESSAGES_PER_SECOND = 1;

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
        \Illuminate\Support\Facades\View::composer(['auth.login', 'auth.register', 'auth.recover', 'profile.show'], function (View $view): void {
            $view->with('telegramAvailable', app(BotClient::class)->isLoginAvailable());
        });
        Gate::define('manage-system-settings', fn (User $user): bool => $user->is_admin);
        RateLimiter::for('telegram', fn (SendTelegramNotificationJob $job): array => [
            Limit::perSecond(self::TELEGRAM_MESSAGES_PER_SECOND)->by('telegram-global'),
            Limit::perSecond(self::TELEGRAM_RECIPIENT_MESSAGES_PER_SECOND)->by(sprintf('telegram-recipient:%d', $job->recipientId)),
        ]);
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
