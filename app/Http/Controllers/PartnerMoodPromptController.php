<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Mood\RequestPartnerMood;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PartnerMoodPromptController extends Controller
{
    public function __construct(private readonly RequestPartnerMood $requestPartnerMood) {}

    public function __invoke(Request $request, Couple $couple): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->requestPartnerMood->execute($actor, $couple);

        return redirect()->route('home')->with('status', __('Партнёру отправлен запрос настроения. Повторное нажатие не отправляет ещё один запрос, пока он ожидает ответа.'));
    }
}
