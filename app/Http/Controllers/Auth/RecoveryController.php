<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RecoverAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RecoverRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class RecoveryController extends Controller
{
    public function __construct(private readonly RecoverAccount $recoverAccount) {}

    public function create(): View
    {
        return view('auth.recover');
    }

    public function store(RecoverRequest $request): RedirectResponse
    {
        $this->recoverAccount->execute(strtolower(trim($request->string('login')->toString())), $request->string('recovery_code')->toString(), $request->string('password')->toString());

        return redirect()->route('login')->with('status', __('Пароль обновлён. Войдите с новым паролем.'));
    }
}
