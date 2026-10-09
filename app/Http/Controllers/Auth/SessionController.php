<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class SessionController extends Controller
{
    public function __construct(private readonly AuthenticateAccount $authenticateAccount) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $this->authenticateAccount->execute($request->string('login')->toString(), $request->string('password')->toString(), $request->ip() ?? 'unknown');
        $request->session()->regenerate();

        return redirect()->intended(route('profile.show'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $userId = Auth::id();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Log::info('logout.completed', ['user_id' => $userId]);

        return redirect()->route('home');
    }
}
