<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\Telegram\ConsumeTelegramLinkCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TelegramLinkRequest;
use App\Models\User;
use App\Services\Telegram\BotClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class TelegramLinkController extends Controller
{
    public function __construct(private readonly ConsumeTelegramLinkCode $consume, private readonly BotClient $bot) {}

    public function __invoke(TelegramLinkRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);
        abort_unless($this->bot->isEnabled(), Response::HTTP_SERVICE_UNAVAILABLE);
        $this->consume->execute($user, $request->string('telegram_code')->toString());

        return redirect()->route('profile.show')->with('status', __('Telegram привязан. Уведомления будут отправляться в ваш чат.'));
    }
}
