<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Mood\RequestPartnerMood;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PartnerMoodPromptController extends Controller
{
    public function __construct(private readonly RequestPartnerMood $requestPartnerMood) {}

    public function __invoke(Request $request, Couple $couple): RedirectResponse|JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $isSelfRequested = $request->routeIs('mood.self.store');
        $prompt = $this->requestPartnerMood->execute($actor, $couple, $isSelfRequested);
        $message = match (true) {
            ! $prompt->wasRecentlyCreated => __('Запрос уже отправлен. Повторить можно через минуту.'),
            $isSelfRequested => __('Вопрос о настроении отправлен вам в Telegram'),
            default => __('Запрос отправлен'),
        };
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('home')->with('toast', $message);
    }
}
