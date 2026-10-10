<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Profiles\UpdateProfile;
use App\Enums\Currency;
use App\Enums\Gender;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Couple;
use App\Models\CoupleMember;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class ProfileController extends Controller
{
    public function __construct(private readonly UpdateProfile $updateProfile) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);
        Gate::authorize('view', $user);

        $encryptedCodes = $request->session()->get('recovery_codes');
        $codes = is_string($encryptedCodes) ? explode("\n", Crypt::decryptString($encryptedCodes)) : [];

        $intended = $request->session()->get('url.intended');
        $invitationUrl = is_string($intended) && str_starts_with($intended, sprintf('%s/', url('invitations'))) ? $intended : null;

        $couple = Couple::query()->whereIn('id', CoupleMember::query()->select('couple_id')->where('active_user_id', $user->id))->first();
        Log::debug('profile.screen_loaded', ['user_id' => $user->id, 'couple_id' => $couple?->id]);

        return view('profile.show', ['user' => $user, 'couple' => $couple, 'currencies' => Currency::cases(), 'recoveryCodes' => $codes, 'pendingInvitationUrl' => $invitationUrl, 'hasTelegram' => $user->telegramAccount()->exists()]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);
        $details = $request->string('payment_details')->toString();
        $this->updateProfile->execute($user, $user, trim($request->string('name')->toString()), Gender::from($request->string('gender')->toString()), $details === '' ? null : $details, $request->enum('currency', Currency::class));

        return redirect()->route('profile.show')->with('status', __('Профиль сохранён.'));
    }
}
