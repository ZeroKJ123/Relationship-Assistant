<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Movies\SaveMovie;
use App\Actions\Movies\VoteForMovie;
use App\Http\Requests\MovieDrawRequest;
use App\Http\Requests\MovieVoteRequest;
use App\Http\Requests\StoreMovieRequest;
use App\Models\Couple;
use App\Models\Movie;
use App\Models\User;
use App\Services\Movies\MovieSelection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class MovieController extends Controller
{
    private const PAGE_SIZE = 12;

    public function __construct(private readonly SaveMovie $save, private readonly VoteForMovie $votes, private readonly MovieSelection $selection) {}

    public function index(Request $request): View
    {
        $actor = $this->getActor($request);
        $couple = Couple::query()->whereRelation('members', 'active_user_id', $actor->id)->first();

        return view('catalogs.movies', ['movies' => Movie::query()->where('user_id', $actor->id)->whereNull('archived_at')->orderByDesc('is_favorite')->latest('id')->paginate(self::PAGE_SIZE), 'couple' => $couple]);
    }

    public function store(StoreMovieRequest $request): RedirectResponse
    {
        $this->save->execute($this->getActor($request), $request->getData());

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
        $drawnId = $request->session()->get('movie_draw');
        $drawn = is_int($drawnId) ? $this->selection->getPool($actor, $couple)->whereKey($drawnId)->first() : null;

        return view('catalogs.movie-choice', ['couple' => $couple, 'nextMovie' => $this->selection->getNext($actor, $couple), 'matches' => $this->selection->getMatches($actor, $couple)->latest('id')->paginate(self::PAGE_SIZE), 'drawn' => $drawn]);
    }

    public function vote(MovieVoteRequest $request, Couple $couple, Movie $movie): RedirectResponse
    {
        $matched = $this->votes->execute($this->getActor($request), $couple, $movie, $request->boolean('liked'));

        return redirect()->route('movies.choice', $couple)->with('status', $matched ? __('Совпадение! Вы оба хотите посмотреть этот фильм.') : __('Выбор сохранён.'));
    }

    public function draw(MovieDrawRequest $request, Couple $couple): RedirectResponse
    {
        $movie = $this->votes->draw($this->getActor($request), $couple, $request->string('pool')->toString() === 'matches');

        return redirect()->route('movies.choice', $couple)->with('movie_draw', $movie?->id)->with('status', $movie === null ? __('В выбранном списке пока нет фильмов. Добавьте фильмы или найдите совпадение.') : __('Рулетка выбрала фильм!'));
    }

    private function getActor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);

        return $actor;
    }
}
