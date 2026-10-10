<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\Telegram\FinishTelegramLogin;
use App\Actions\Auth\Telegram\StartTelegramLogin;
use App\Http\Controllers\Controller;
use App\Models\TelegramLoginAttempt;
use App\Models\User;
use App\Services\Telegram\ProfilePhoto;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

final class TelegramLoginController extends Controller
{
    public function __construct(private readonly StartTelegramLogin $start, private readonly FinishTelegramLogin $finish, private readonly ProfilePhoto $photo) {}

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $result = $this->start->execute($actor instanceof User ? $actor : null);
        $request->session()->put('telegram_login', ['attempt_id' => $result['attempt']->id, 'browser_token' => $result['browser_token'], 'start_token' => $result['start_token']]);

        return redirect()->route('telegram.login.show', $result['attempt']->id);
    }

    public function show(Request $request, TelegramLoginAttempt $attempt): View
    {
        $this->requireSession($request, $attempt);
        $startToken = $request->session()->get('telegram_login.start_token');
        abort_unless(is_string($startToken), Response::HTTP_FORBIDDEN);

        return view('auth.telegram-login', ['attempt' => $attempt, 'deepLink' => sprintf('https://t.me/%s?start=%s', config()->string('services.telegram.bot_username'), $startToken)]);
    }

    public function update(Request $request, TelegramLoginAttempt $attempt): RedirectResponse
    {
        $this->requireSession($request, $attempt);
        $browserToken = $request->session()->get('telegram_login.browser_token');
        abort_unless(is_string($browserToken), Response::HTTP_FORBIDDEN);
        $actor = $request->user();
        $result = $this->finish->execute($attempt->id, $browserToken, $actor instanceof User ? $actor : null);
        $request->session()->forget('telegram_login');
        Auth::login($result['user']);
        $request->session()->regenerate();
        if ($result['recovery_codes'] !== []) {
            $request->session()->flash('recovery_codes', Crypt::encryptString(implode("\n", $result['recovery_codes'])));
        }
        $this->photo->import($result['user'], $result['account']);

        return redirect()->route('profile.show')->with('status', __('Telegram подтверждён. Вход выполнен.'));
    }

    public function destroy(Request $request, TelegramLoginAttempt $attempt): RedirectResponse
    {
        $this->requireSession($request, $attempt);
        $browserToken = $request->session()->get('telegram_login.browser_token');
        abort_unless(is_string($browserToken), Response::HTTP_FORBIDDEN);
        $this->start->cancel($attempt->id, $browserToken);
        $request->session()->forget('telegram_login');

        return redirect()->route($request->user() === null ? 'login' : 'profile.show');
    }

    private function requireSession(Request $request, TelegramLoginAttempt $attempt): void
    {
        abort_unless($request->session()->get('telegram_login.attempt_id') === $attempt->id && $attempt->user_id === $request->user()?->getAuthIdentifier(), Response::HTTP_FORBIDDEN);
    }
}
