<?php

declare(strict_types=1);

namespace App\Actions\Movies;

use App\Data\Catalogs\MovieData;
use App\Models\Movie;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

final class SaveMovie
{
    public function execute(User $actor, MovieData $data): Movie
    {
        $title = trim($data->title);
        $year = $data->year;
        $description = $data->description;
        $isFavorite = $data->isFavorite;
        Validator::make(['title' => $title, 'year' => $year, 'description' => $description, 'link' => $data->link], ['title' => ['required', 'string', 'max:150'], 'year' => ['nullable', 'integer', 'between:1888,2200'], 'description' => ['nullable', 'string', 'max:1000'], 'link' => ['nullable', 'url:http,https', 'max:1000']])->validate();
        $identity = hash('sha256', sprintf('%s|%s', mb_strtolower(preg_replace('/\s+/u', ' ', $title) ?? $title), $year ?? ''));

        return DB::transaction(function () use ($actor, $title, $year, $description, $isFavorite, $identity, $data): Movie {
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $movie = Movie::query()->where('user_id', $actor->id)->where('identity_hash', $identity)->lockForUpdate()->first() ?? new Movie;
            $movie->forceFill(['user_id' => $actor->id, 'identity_hash' => $identity, 'title' => $title, 'year' => $year, 'description' => $description, 'link' => $data->link, 'is_favorite' => $isFavorite, 'archived_at' => null])->save();
            DB::afterCommit(fn () => Log::info('movie.saved', ['movie_id' => $movie->id, 'actor_id' => $actor->id]));

            return $movie;
        }, attempts: 3);
    }

    public function archive(User $actor, Movie $movie): void
    {
        DB::transaction(function () use ($actor, $movie): void {
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $current = Movie::query()->whereKey($movie->id)->where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
            $current->forceFill(['archived_at' => now()])->save();
            DB::afterCommit(fn () => Log::info('movie.archived', ['movie_id' => $current->id, 'actor_id' => $actor->id]));
        }, attempts: 3);
    }
}
