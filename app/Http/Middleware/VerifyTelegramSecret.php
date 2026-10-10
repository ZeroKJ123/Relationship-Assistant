<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Telegram\BotClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class VerifyTelegramSecret
{
    private const MAXIMUM_PAYLOAD_BYTES = 65536;

    public function __construct(private readonly BotClient $bot) {}

    /** @param Closure(Request): SymfonyResponse $next */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $secret = config('services.telegram.webhook_secret');
        $provided = $request->header('X-Telegram-Bot-Api-Secret-Token');
        abort_unless($this->bot->isLoginAvailable() && is_string($secret) && is_string($provided) && hash_equals($secret, $provided), Response::HTTP_FORBIDDEN);
        abort_if(strlen($request->getContent()) > self::MAXIMUM_PAYLOAD_BYTES, Response::HTTP_REQUEST_ENTITY_TOO_LARGE);

        return $next($request);
    }
}
