<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Movies\ManageMovieChoice;
use App\Http\Requests\MovieChoiceRequest;
use App\Models\Couple;
use App\Models\Movie;
use App\Models\MovieChoice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class MovieChoiceController extends Controller
{
    public function __construct(private readonly ManageMovieChoice $choices) {}

    public function store(MovieChoiceRequest $request, Couple $couple): RedirectResponse
    {
        $this->choices->create($this->getActor($request), $couple, $request->string('mode')->toString(), $request->string('operation_key')->toString());

        return redirect()->route('movies.choice', $couple);
    }

    public function update(MovieChoiceRequest $request, Couple $couple, MovieChoice $movieChoice): RedirectResponse
    {
        $actor = $this->getActor($request);
        $command = $request->string('command')->toString();
        if ($command === 'answer') {
            $this->choices->answer($actor, $couple, $movieChoice, $request->string('answer')->toString() === 'accept');
        }
        if ($command === 'vote') {
            $this->choices->vote($actor, $couple, $movieChoice, Movie::query()->findOrFail($request->integer('movie_id')), $request->boolean('liked'));
        }
        if ($command === 'spin') {
            $this->choices->draw($actor, $couple, $movieChoice);
        }

        return redirect()->route('movies.choice', $couple);
    }

    private function getActor(MovieChoiceRequest $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);

        return $actor;
    }
}
