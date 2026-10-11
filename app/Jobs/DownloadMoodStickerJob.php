<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Mood\AddMoodSticker;
use App\Enums\MoodStickerFormat;
use App\Enums\MoodStickerStatus;
use App\Models\Couple;
use App\Models\MoodEntry;
use App\Models\User;
use App\Services\Telegram\MoodStickers;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class DownloadMoodStickerJob implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(public readonly int $entryId, public readonly string $fileId, public readonly MoodStickerFormat $format)
    {
        $this->afterCommit();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 15, 60, 120];
    }

    public function handle(MoodStickers $stickers, AddMoodSticker $addSticker): void
    {
        $entry = MoodEntry::query()->find($this->entryId);
        if ($entry === null || $entry->sticker_status !== MoodStickerStatus::Pending) {
            return;
        }
        $path = $stickers->download($entry->id, $this->fileId, $this->format);
        try {
            $saved = $addSticker->execute(User::query()->findOrFail($entry->user_id), Couple::query()->findOrFail($entry->couple_id), $entry, $path, $this->format);
        } catch (AuthorizationException|ValidationException) {
            $saved = false;
        }
        if (! $saved) {
            Storage::disk(MoodStickers::DISK)->delete($path);
            $this->failed();
        }
    }

    public function failed(?Throwable $exception = null): void
    {
        $changed = MoodEntry::query()->whereKey($this->entryId)->where('sticker_status', MoodStickerStatus::Pending)->update(['sticker_status' => MoodStickerStatus::Failed]);
        if ($changed === 1) {
            Storage::disk(MoodStickers::DISK)->delete(app(MoodStickers::class)->getStoragePath($this->entryId, $this->fileId, $this->format));
        }
        Log::warning('mood.sticker_unavailable', ['entry_id' => $this->entryId, 'reason' => 'download_or_state']);
    }
}
