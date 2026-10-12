<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Http\Request;

final class PanelAccess
{
    public const SESSION_SECONDS = 1800;

    public function isOwner(?User $user): bool
    {
        $login = config('admin_panel.login');
        $hash = config('admin_panel.password_hash');

        return $user !== null && is_string($login) && $login !== '' && $user->login === $login && is_string($hash) && $hash !== '';
    }

    public function isUnlocked(Request $request, User $user): bool
    {
        if (! $request->hasSession() || ! $this->isOwner($user)) {
            return false;
        }
        $until = $request->session()->get('admin_panel.until');
        $fingerprint = $request->session()->get('admin_panel.fingerprint');
        $hash = config('admin_panel.password_hash');

        return $request->session()->get('admin_panel.owner') === $user->id && is_int($until) && $until > now()->timestamp && is_string($hash) && is_string($fingerprint) && hash_equals(hash('sha256', $hash), $fingerprint);
    }

    public function clear(Request $request): void
    {
        $request->session()->forget('admin_panel');
    }
}
