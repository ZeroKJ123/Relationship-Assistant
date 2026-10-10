<?php

declare(strict_types=1);

namespace App\Http\Controllers\Telegram;

use App\Data\Telegram\IncomingUpdate;
use App\Http\Controllers\Controller;
use App\Services\Telegram\BotClient;
use App\Services\Telegram\WebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

final class WebhookController extends Controller
{
    public function __construct(private readonly WebhookHandler $handler, private readonly BotClient $bot) {}

    public function __invoke(Request $request): JsonResponse
    {
        $update = IncomingUpdate::fromArray($request->json()->all());
        if ($update === null) {
            return response()->json(['ok' => true]);
        }
        try {
            $reply = $this->handler->execute($update, hash('sha256', $request->getContent()));
        } catch (Throwable $exception) {
            Log::error('telegram.update_failed', ['update_id' => $update->updateId, 'exception_class' => $exception::class]);

            return response()->json(['ok' => false], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        if ($update->callbackId !== null) {
            $this->bot->answerCallback($update->callbackId);
        }

        return response()->json($reply === [] ? ['ok' => true] : $reply);
    }
}
