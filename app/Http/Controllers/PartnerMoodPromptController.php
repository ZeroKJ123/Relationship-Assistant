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
        $prompt = $this->requestPartnerMood->execute($actor, $couple);

        $message = $prompt->wasRecentlyCreated ? __('Запрос отправлен') : __('Запрос уже отправлен. Повторить можно через минуту.');
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('home')->with('toast', $message);
    }
}
