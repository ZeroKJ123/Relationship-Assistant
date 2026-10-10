<?php

declare(strict_types=1);

namespace App\Actions\Movies;

use App\Models\Couple;
use App\Models\Movie;
use App\Models\MovieVote;
use App\Models\User;
use App\Services\Movies\MovieSelection;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class VoteForMovie
{
    public function __construct(private readonly RequestContext $context, private readonly MovieSelection $selection) {}

    public function execute(User $actor, Couple $couple, Movie $movie, bool $liked): bool
    {
        return DB::transaction(function () use ($actor, $couple, $movie, $liked): bool {
            $current = $this->context->lockCouple($actor, $couple);
            $allowed = Movie::query()->whereKey($movie->id)->whereIn('user_id', $current->members()->select('user_id'))->whereNull('archived_at')->lockForUpdate()->firstOrFail();
            $vote = MovieVote::query()->where('couple_id', $current->id)->where('user_id', $actor->id)->where('identity_hash', $allowed->identity_hash)->lockForUpdate()->first() ?? new MovieVote;
            if ($vote->exists && $vote->liked !== $liked) {
                $this->context->deny('movie_vote_already_saved', coupleId: $current->id);
            }
            $vote->forceFill(['couple_id' => $current->id, 'user_id' => $actor->id, 'identity_hash' => $allowed->identity_hash, 'liked' => $liked])->save();
            DB::afterCommit(fn () => Log::info('movie.voted', ['movie_id' => $allowed->id, 'actor_id' => $actor->id, 'couple_id' => $current->id]));

            return $this->selection->getMatches($actor, $current)->where('identity_hash', $allowed->identity_hash)->exists();
        }, attempts: 3);
    }

    public function draw(User $actor, Couple $couple, bool $onlyMatches): ?Movie
    {
        return DB::transaction(function () use ($actor, $couple, $onlyMatches): ?Movie {
            $current = $this->context->lockCouple($actor, $couple);
            $pool = $onlyMatches ? $this->selection->getMatches($actor, $current) : $this->selection->getPool($actor, $current);
            $movie = $pool->inRandomOrder()->first();
            Log::debug('movie.drawn', ['couple_id' => $current->id, 'movie_id' => $movie?->id, 'actor_id' => $actor->id]);

            return $movie;
        }, attempts: 3);
    }
}
