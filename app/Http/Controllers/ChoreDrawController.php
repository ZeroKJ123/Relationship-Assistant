<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Duties\ManageChoreDraw;
use App\Http\Requests\ChoreDrawAnswerRequest;
use App\Http\Requests\StoreChoreDrawRequest;
use App\Models\ChoreDraw;
use App\Models\Couple;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ChoreDrawController extends Controller
{
    /** Сколько раз пара имён повторяется на колесе, чтобы оно выглядело рулеткой. */
    private const WHEEL_REPEATS = 4;

    private const FULL_TURN = 360;

    private const SPIN_TURNS = 4;

    public function __construct(private readonly ManageChoreDraw $draws) {}

    public function index(Request $request, Couple $couple): View
    {
        Gate::authorize('view', $couple);

        return view('duties.draws', ['couple' => $couple, 'operationKey' => Str::uuid()->toString()]);
    }

    public function store(StoreChoreDrawRequest $request, Couple $couple): RedirectResponse
    {
        $dueAt = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $request->string('due_at')->toString(), $couple->timezone);
        if ($dueAt === null) {
            throw ValidationException::withMessages(['due_at' => __('Укажите корректный срок.')]);
        }
        $draw = $this->draws->create($this->getActor($request), $couple, $request->string('description')->toString(), $dueAt, $request->string('operation_key')->toString());

        return redirect()->route('chores.show', [$couple, $draw]);
    }

    public function show(Request $request, Couple $couple, ChoreDraw $choreDraw): View
    {
        Gate::authorize('view', $couple);
        abort_unless($choreDraw->couple_id === $couple->id, Response::HTTP_NOT_FOUND);

        $participants = User::query()->whereIn('id', [$choreDraw->first_user_id, $choreDraw->second_user_id])->orderBy('id')->get();
        $sectors = collect(range(1, self::WHEEL_REPEATS))->flatMap(fn (): array => $participants->all())->values();
        $executorIndex = (int) $participants->search(fn (User $participant): bool => $participant->id === $choreDraw->executor_id);
        $winningSector = $executorIndex + $participants->count() * ($choreDraw->id % self::WHEEL_REPEATS);
        $rotation = self::FULL_TURN * self::SPIN_TURNS - (($winningSector + 0.5) * self::FULL_TURN / max(1, $sectors->count()));

        return view('duties.draw', ['couple' => $couple, 'draw' => $choreDraw, 'actor' => $this->getActor($request), 'sectors' => $sectors, 'rotation' => $rotation, 'executor' => $choreDraw->executor_id === null ? null : $participants->firstWhere('id', $choreDraw->executor_id) ?? User::query()->findOrFail($choreDraw->executor_id)]);
    }

    public function update(ChoreDrawAnswerRequest $request, Couple $couple, ChoreDraw $choreDraw): RedirectResponse
    {
        $this->draws->answer($this->getActor($request), $couple, $choreDraw, $request->string('answer')->toString() === 'accept');

        return redirect()->route('chores.show', [$couple, $choreDraw]);
    }

    private function getActor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);

        return $actor;
    }
}
