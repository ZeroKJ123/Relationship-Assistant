<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateThemeRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

final class ProfileThemeController extends Controller
{
    public function __invoke(UpdateThemeRequest $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $actor->forceFill(['theme' => $request->string('theme')->toString()])->save();
        Log::info('profile.theme_changed', ['user_id' => $actor->id, 'theme' => $actor->theme]);

        return redirect()->route('profile.show')->with('status', __('Тема оформления сохранена.'));
    }
}
