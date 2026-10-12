<?php

declare(strict_types=1);

namespace App\Actions\Movies;

use App\Enums\NotificationKind;
use App\Enums\SelectionStatus;
use App\Models\Couple;
use App\Models\Movie;
use App\Models\MovieChoice;
use App\Models\User;
use App\Services\Movies\MovieSelection;
use App\Services\Notifications\NotificationOutbox;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

final class ManageMovieChoice
{
    public const MAXIMUM_FILMS = 100;

    public function __construct(private readonly RequestContext $context, private readonly MovieSelection $selection, private readonly NotificationOutbox $outbox) {}

    public function create(User $actor, Couple $couple, string $mode, string $operationKey): MovieChoice
    {
        Validator::make(['mode' => $mode, 'key' => $operationKey], ['mode' => ['in:swipe,roulette'], 'key' => ['uuid']])->validate();

        return DB::transaction(function () use ($actor, $couple, $mode, $operationKey): MovieChoice {
            $current = $this->context->lockCouple($actor, $couple);
            $repeated = MovieChoice::query()->where('first_user_id', $actor->id)->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($repeated !== null) {
                if ($repeated->couple_id !== $current->id || $repeated->mode !== $mode) {
                    $this->context->deny('changed_selection_payload');
                }

                return $repeated;
            }
            if (MovieChoice::query()->where('couple_id', $current->id)->whereIn('status', [SelectionStatus::Waiting, SelectionStatus::Active])->lockForUpdate()->first() !== null) {
                $this->context->deny('selection_already_active');
            }
            $pool = $this->selection->getPool($actor, $current)->orderBy('id')->limit(self::MAXIMUM_FILMS + 1)->pluck('id')->all();
            if (count($pool) === 0 || count($pool) > self::MAXIMUM_FILMS) {
                $this->context->deny('movie_pool_size');
            }
            $partner = $current->members()->where('user_id', '!=', $actor->id)->sole();
            $choice = new MovieChoice;
            $choice->forceFill(['couple_id' => $current->id, 'first_user_id' => $actor->id, 'second_user_id' => $partner->user_id, 'operation_key' => $operationKey, 'mode' => $mode, 'status' => $mode === 'swipe' ? SelectionStatus::Waiting : SelectionStatus::Active, 'first_ready' => false, 'second_ready' => false, 'pool' => $pool, 'votes' => []])->save();
            if ($mode === 'swipe') {
                $this->outbox->record($current->id, $partner->user_id, NotificationKind::MovieChoice, $choice->id);
            }
            DB::afterCommit(fn () => Log::info('movies.selection_created', ['selection_id' => $choice->id, 'actor_id' => $actor->id, 'mode' => $mode]));

            return $choice;
        }, attempts: 3);
    }

    public function answer(User $actor, Couple $couple, MovieChoice $choice, bool $accepted): void
    {
        DB::transaction(function () use ($actor, $couple, $choice, $accepted): void {
            $current = $this->lock($actor, $couple, $choice);
            if ($current->status !== SelectionStatus::Waiting) {
                return;
            }
            if (! $accepted) {
                $current->forceFill(['status' => SelectionStatus::Declined])->save();

                return;
            }
            $field = $actor->id === $current->first_user_id ? 'first_ready' : 'second_ready';
            $current->setAttribute($field, true);
            if ($current->first_ready && $current->second_ready) {
                $current->status = SelectionStatus::Active;
            }
            $current->save();
            DB::afterCommit(fn () => Log::info('movies.selection_answered', ['selection_id' => $current->id, 'actor_id' => $actor->id, 'status' => $current->status->value]));
        }, attempts: 3);
    }

    public function vote(User $actor, Couple $couple, MovieChoice $choice, Movie $movie, bool $liked): void
    {
        DB::transaction(function () use ($actor, $couple, $choice, $movie, $liked): void {
            $current = $this->lock($actor, $couple, $choice);
            if ($current->status === SelectionStatus::Matched) {
                return;
            }
            if ($current->status !== SelectionStatus::Active || $current->mode !== 'swipe' || ! in_array($movie->id, $current->pool, true)) {
                $this->context->deny('selection_not_active');
            }
            $votes = $current->votes;
            if (isset($votes[$actor->id][$movie->id])) {
                if ($votes[$actor->id][$movie->id] !== $liked) {
                    $this->context->deny('movie_vote_already_saved');
                }

                return;
            }
            $votes[$actor->id][$movie->id] = $liked;
            $current->votes = $votes;
            if ($liked && ($votes[$current->first_user_id][$movie->id] ?? false) && ($votes[$current->second_user_id][$movie->id] ?? false)) {
                $current->status = SelectionStatus::Matched;
                $current->selected_movie_id = $movie->id;
            }
            if ($current->status === SelectionStatus::Active && count($votes[$current->first_user_id] ?? []) === count($current->pool) && count($votes[$current->second_user_id] ?? []) === count($current->pool)) {
                $current->status = SelectionStatus::Exhausted;
            }
            $current->save();
            DB::afterCommit(fn () => Log::info('movies.selection_voted', ['selection_id' => $current->id, 'movie_id' => $movie->id, 'actor_id' => $actor->id, 'status' => $current->status->value]));
        }, attempts: 3);
    }

    public function draw(User $actor, Couple $couple, MovieChoice $choice): void
    {
        DB::transaction(function () use ($actor, $couple, $choice): void {
            $current = $this->lock($actor, $couple, $choice);
            if ($current->status === SelectionStatus::Matched) {
                return;
            }
            if ($current->mode !== 'roulette' || $current->status !== SelectionStatus::Active) {
                $this->context->deny('selection_not_active');
            }
            $index = random_int(0, count($current->pool) - 1);
            $current->forceFill(['selected_movie_id' => $current->pool[$index], 'status' => SelectionStatus::Matched])->save();
            DB::afterCommit(fn () => Log::info('movies.selection_drawn', ['selection_id' => $current->id, 'movie_id' => $current->selected_movie_id]));
        }, attempts: 3);
    }

    private function lock(User $actor, Couple $couple, MovieChoice $choice): MovieChoice
    {
        $current = $this->context->lockCouple($actor, $couple);

        return MovieChoice::query()->whereKey($choice->id)->where('couple_id', $current->id)->lockForUpdate()->firstOrFail();
    }
}
