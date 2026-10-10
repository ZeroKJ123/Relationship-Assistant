<?php

declare(strict_types=1);

namespace App\Services\Movies;

use App\Models\Couple;
use App\Models\Movie;
use App\Models\MovieVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final class MovieSelection
{
    private const PARTNER_COUNT = 2;

    /** @return Builder<Movie> */
    public function getPool(User $actor, Couple $couple): Builder
    {
        Gate::forUser($actor)->authorize('view', $couple);
        $owners = $couple->members()->whereNotNull('active_user_id')->select('user_id');
        $ids = Movie::query()->selectRaw('MIN(id)')->whereIn('user_id', $owners)->whereNull('archived_at')->groupBy('identity_hash');

        return Movie::query()->whereIn('id', $ids);
    }

    /** @return Builder<Movie> */
    public function getMatches(User $actor, Couple $couple): Builder
    {
        $keys = MovieVote::query()->select('identity_hash')->where('couple_id', $couple->id)->where('liked', true)->groupBy('identity_hash')->havingRaw(sprintf('COUNT(*) = %d', self::PARTNER_COUNT));

        return $this->getPool($actor, $couple)->whereIn('identity_hash', $keys);
    }

    public function getNext(User $actor, Couple $couple): ?Movie
    {
        $voted = MovieVote::query()->where('couple_id', $couple->id)->where('user_id', $actor->id)->select('identity_hash');

        return $this->getPool($actor, $couple)->whereNotIn('identity_hash', $voted)->orderBy('id')->first();
    }
}
