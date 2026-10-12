<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Movies\ManageMovieChoice;
use App\Actions\Movies\SaveMovie;
use App\Enums\SelectionStatus;
use App\Http\Requests\MovieDrawRequest;
use App\Http\Requests\MovieVoteRequest;
use App\Http\Requests\StoreMovieRequest;
use App\Models\Couple;
use App\Models\Movie;
use App\Models\MovieChoice;
use App\Models\User;
use App\Services\Movies\MoviePhotos;
use App\Services\Movies\MovieSelection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class MovieController extends Controller
{
    private const PAGE_SIZE = 12;

    private const FULL_TURN = 360;

    private const SPIN_TURNS = 3;

    public function __construct(private readonly SaveMovie $save, private readonly MovieSelection $selection, private readonly MoviePhotos $photos, private readonly ManageMovieChoice $choices) {}

    public function index(Request $request): View
    {
        $actor = $this->getActor($request);
        $couple = Couple::query()->whereRelation('members', 'active_user_id', $actor->id)->first();

        return view('catalogs.movies', ['movies' => Movie::query()->where('user_id', $actor->id)->whereNull('archived_at')->orderByDesc('is_favorite')->latest('id')->paginate(self::PAGE_SIZE), 'couple' => $couple]);
    }

    public function store(StoreMovieRequest $request): RedirectResponse
    {
        $this->photos->save($this->getActor($request), $request->getData(), $request->file('photo'));

        return redirect()->route('movies.index')->with('status', __('Фильм сохранён в вашем списке.'));
    }

    public function destroy(Request $request, Movie $movie): RedirectResponse
    {
        $this->save->archive($this->getActor($request), $movie);

        return redirect()->route('movies.index')->with('status', __('Фильм убран из списка.'));
    }

    public function show(Request $request, Couple $couple): View
    {
        $actor = $this->getActor($request);
        Gate::forUser($actor)->authorize('update', $couple);
        $this->selection->getPool($actor, $couple);
        $choice = MovieChoice::query()->where('couple_id', $couple->id)->latest('id')->first();
        $films = $choice === null ? $this->selection->getPool($actor, $couple)->orderBy('id')->limit(ManageMovieChoice::MAXIMUM_FILMS)->get() : Movie::query()->whereIn('id', $choice->pool)->orderBy('id')->get();
        $votes = $choice?->votes[$actor->id] ?? [];
        $next = $films->first(fn (Movie $film): bool => ! array_key_exists($film->id, $votes));
        $selected = $choice?->selected_movie_id === null ? null : $films->firstWhere('id', $choice->selected_movie_id);
        $index = $selected === null ? 0 : $films->search(fn (Movie $film): bool => $film->id === $selected->id);

        return view('catalogs.movie-choice', ['couple' => $couple, 'choice' => $choice, 'films' => $films, 'nextMovie' => $next, 'selected' => $selected, 'actor' => $actor, 'operationKey' => Str::uuid()->toString(), 'rotation' => self::FULL_TURN * (self::SPIN_TURNS + 1) - (($index + 0.5) * self::FULL_TURN / max(1, $films->count()))]);
    }

    public function vote(MovieVoteRequest $request, Couple $couple, Movie $movie): RedirectResponse
    {
        $choice = MovieChoice::query()->where('couple_id', $couple->id)->where('status', SelectionStatus::Active)->where('mode', 'swipe')->latest('id')->firstOrFail();
        $this->choices->vote($this->getActor($request), $couple, $choice, $movie, $request->boolean('liked'));

        return redirect()->route('movies.choice', $couple);
    }

    public function draw(MovieDrawRequest $request, Couple $couple): RedirectResponse
    {
        Gate::forUser($this->getActor($request))->authorize('update', $couple);
        abort(Response::HTTP_GONE, __('Откройте новый совместный выбор фильма.'));
    }

    private function getActor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);

        return $actor;
    }
}
