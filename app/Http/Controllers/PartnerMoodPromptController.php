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
        $prompt = $this->requestPartnerMood->execute($actor, $couple);

        return redirect()->route('home')->with('status', $prompt->wasRecentlyCreated
            ? __('Запрос настроения создан. Уведомление будет отправлено в подключённый Telegram партнёра.')
            : __('Запрос уже создан. Отправить его повторно можно через минуту после предыдущего нажатия.'));
    }
}
