<?php

declare(strict_types=1);

namespace App\Actions\Profiles;

use App\Models\User;
use App\Services\Media\PrivatePhotos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

final class UpdateAvatar
{
    public function __construct(private readonly PrivatePhotos $photos) {}

    public function execute(User $actor, User $user, UploadedFile $photo): void
    {
        Gate::forUser($actor)->authorize('update', $user);
        $path = $this->photos->store($photo);
        try {
            $oldPath = DB::transaction(function () use ($actor, $user, $path): ?string {
                $current = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('update', $current);
                $oldPath = $current->avatar_path;
                $current->forceFill(['avatar_path' => $path])->save();

                return $oldPath;
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->photos->discard($path);
            throw $exception;
        }
        DB::afterCommit(function () use ($oldPath, $user): void {
            try {
                $this->photos->discard($oldPath);
            } catch (Throwable $exception) {
                Log::warning('profile.avatar_cleanup_failed', ['user_id' => $user->id, 'exception_class' => $exception::class]);
            }
            Log::info('profile.avatar_updated', ['user_id' => $user->id]);
        });
    }
}
