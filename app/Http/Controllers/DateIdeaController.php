<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Dates\DeleteDateIdea;
use App\Actions\Dates\SaveDateIdea;
use App\Http\Requests\DeleteDateIdeaRequest;
use App\Http\Requests\StoreDateIdeaRequest;
use App\Models\Couple;
use App\Models\DateIdea;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DateIdeaController extends Controller
{
    private const FULL_TURN = 360;

    private const SPIN_TURNS = 5;

    private const MINIMUM_FOR_SPIN = 2;

    private const PICKED_KEY = 'picked_date_idea';

    public function __construct(private readonly SaveDateIdea $save, private readonly DeleteDateIdea $delete) {}

    public function index(Request $request, Couple $couple): View
    {
        Gate::authorize('view', $couple);
        $ideas = DateIdea::query()->where('couple_id', $couple->id)->orderBy('id')->limit(SaveDateIdea::MAXIMUM_PER_COUPLE)->get();
        $pickedIndex = $ideas->search(fn (DateIdea $idea): bool => $idea->id === $request->session()->get(self::PICKED_KEY));
        $picked = $pickedIndex === false ? null : $ideas->get((int) $pickedIndex);

        return view('date-ideas.index', [
            'couple' => $couple, 'ideas' => $ideas, 'picked' => $picked, 'canEdit' => Gate::allows('update', $couple), 'canSpin' => $ideas->count() >= self::MINIMUM_FOR_SPIN,
            'rotation' => $picked === null ? 0 : self::FULL_TURN * self::SPIN_TURNS - (((int) $pickedIndex + 0.5) * self::FULL_TURN / $ideas->count()),
            'maximum' => SaveDateIdea::MAXIMUM_PER_COUPLE, 'operationKey' => Str::uuid()->toString(),
        ]);
    }

    public function store(StoreDateIdeaRequest $request, Couple $couple): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->save->execute($actor, $couple, $request->string('title')->trim()->toString(), $request->string('operation_key')->toString());

        return redirect()->route('date_ideas.index', $couple)->with('status', __('Идея добавлена на колесо.'));
    }

    public function spin(Couple $couple): RedirectResponse
    {
        Gate::authorize('update', $couple);
        $ids = DateIdea::query()->where('couple_id', $couple->id)->orderBy('id')->limit(SaveDateIdea::MAXIMUM_PER_COUPLE)->pluck('id');
        if ($ids->count() < self::MINIMUM_FOR_SPIN) {
            throw ValidationException::withMessages(['title' => __('Добавьте хотя бы две идеи, чтобы крутить колесо.')]);
        }

        return redirect()->route('date_ideas.index', $couple)->with(self::PICKED_KEY, $ids->get(random_int(0, $ids->count() - 1)));
    }

    public function destroy(DeleteDateIdeaRequest $request, Couple $couple, DateIdea $dateIdea): RedirectResponse
    {
        abort_unless($dateIdea->couple_id === $couple->id, Response::HTTP_NOT_FOUND);
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->delete->execute($actor, $couple, $dateIdea);

        return redirect()->route('date_ideas.index', $couple)->with('status', __('Идея удалена.'));
    }
}
