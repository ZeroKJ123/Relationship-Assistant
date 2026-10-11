<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MoodStickerStatus;
use App\Models\MoodEntry;
use App\Services\Telegram\MoodStickers;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MoodStickerController extends Controller
{
    public function __invoke(MoodEntry $moodEntry): StreamedResponse
    {
        Gate::authorize('view', $moodEntry->couple);
        abort_unless($moodEntry->sticker_status === MoodStickerStatus::Ready && $moodEntry->sticker_path !== null && $moodEntry->sticker_format !== null, Response::HTTP_NOT_FOUND);
        $disk = Storage::disk(MoodStickers::DISK);
        abort_unless($disk->exists($moodEntry->sticker_path), Response::HTTP_NOT_FOUND);

        return $disk->response($moodEntry->sticker_path, null, ['Content-Type' => $moodEntry->sticker_format->getMimeType(), 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'"], 'inline');
    }
}
