<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

final class RegistrationController extends Controller
{
    public function __construct(private readonly RegisterAccount $registerAccount) {}

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $result = $this->registerAccount->execute($request->getData());
        Auth::login($result['user']);
        $request->session()->regenerate();
        $request->session()->flash('recovery_codes', Crypt::encryptString(implode("\n", $result['recovery_codes'])));

        return redirect()->route('profile.show');
    }
}
