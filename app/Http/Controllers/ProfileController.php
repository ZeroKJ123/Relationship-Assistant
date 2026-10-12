<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Profiles\UpdateProfile;
use App\Enums\Currency;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Car;
use App\Models\Couple;
use App\Models\CoupleMember;
use App\Models\Dish;
use App\Models\User;
use App\Services\Appearance\AppearanceStore;
use App\Services\Telegram\BotClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class ProfileController extends Controller
{
    public function __construct(private readonly UpdateProfile $updateProfile, private readonly BotClient $bot, private readonly AppearanceStore $appearance) {}

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

        $username = config('services.telegram.bot_username');
        $botUrl = is_string($username) && preg_match('/^[A-Za-z0-9_]{5,32}$/D', $username) === 1 ? sprintf('https://t.me/%s', $username) : null;

        return view('profile.show', ['themes' => $this->appearance->selectableThemes(), 'themeId' => $this->appearance->currentId(), 'themeName' => $this->appearance->theme()['name'], 'recipeCount' => Dish::query()->where('user_id', $user->id)->count(), 'cars' => Car::query()->where('user_id', $user->id)->orderBy('id')->limit(10)->get(), 'user' => $user, 'couple' => $couple, 'currencies' => Currency::cases(), 'recoveryCodes' => $codes, 'pendingInvitationUrl' => $invitationUrl, 'hasTelegram' => $user->telegramAccount()->exists(), 'telegramLinkAvailable' => $this->bot->isEnabled(), 'telegramBotUrl' => $botUrl]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->updateProfile->execute($user, $user, $request->getData());

        return redirect()->route('profile.show')->with('status', __('Профиль сохранён.'));
    }
}
