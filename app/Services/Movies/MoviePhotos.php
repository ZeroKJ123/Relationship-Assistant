<?php

declare(strict_types=1);

namespace App\Services\Movies;

use App\Actions\Movies\SaveMovie;
use App\Data\Catalogs\MovieData;
use App\Models\Movie;
use App\Models\User;
use App\Services\Media\PrivatePhotos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class MoviePhotos
{
    public function __construct(private readonly PrivatePhotos $photos, private readonly SaveMovie $save) {}

    public function save(User $actor, MovieData $data, ?UploadedFile $photo): Movie
    {
        $path = $this->photos->store($photo);
        try {
            [$movie,$oldPath] = DB::transaction(function () use ($actor, $data, $path): array {
                $movie = $this->save->execute($actor, $data);
                $oldPath = null;
                if ($path !== null) {
                    $oldPath = $movie->photo_path;
                    $movie->forceFill(['photo_path' => $path])->save();
                }

                return [$movie, $oldPath];
            }, attempts: 3);
        } catch (Throwable $exception) {
            try {
                $this->photos->discard($path);
            } catch (Throwable $cleanup) {
                Log::warning('movie.photo_cleanup_failed', ['actor_id' => $actor->id, 'reason' => 'rollback', 'exception_type' => $cleanup::class]);
            }
            throw $exception;
        }
        DB::afterCommit(function () use ($oldPath, $movie): void {
            try {
                $this->photos->discard($oldPath);
            } catch (Throwable $cleanup) {
                Log::warning('movie.photo_cleanup_failed', ['movie_id' => $movie->id, 'reason' => 'replaced', 'exception_type' => $cleanup::class]);
            }
        });

        return $movie;
    }
}
