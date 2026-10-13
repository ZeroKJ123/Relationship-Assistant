<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLocaleRequest;
use App\Models\User;
use App\Services\Localization\LocalePreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

final class LocaleController extends Controller
{
    public function __construct(private readonly LocalePreference $preference) {}

    public function __invoke(UpdateLocaleRequest $request): RedirectResponse
    {
        $locale = $request->getData();
        $this->preference->remember($locale);
        app()->setLocale($locale->value);
        $actor = $request->user();
        if (! $actor instanceof User) {
            return redirect()->back(fallback: route('home'));
        }
        $actor->forceFill(['locale' => $locale])->save();
        Log::info('profile.locale_changed', ['user_id' => $actor->id, 'locale' => $locale->value]);

        return redirect()->route('profile.show')->with('status', __('Язык сохранён.'));
    }
}
