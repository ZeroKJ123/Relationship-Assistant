<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Duties\ManageChoreDraw;
use App\Actions\Movies\ManageMovieChoice;
use App\Enums\SelectionStatus;
use App\Http\Resources\SelectionStateResource;
use App\Models\ChoreDraw;
use App\Models\Couple;
use App\Models\MovieChoice;
use App\Models\User;
use App\Services\Realtime\SelectionPresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class SelectionPresenceController extends Controller
{
    public function __construct(private readonly SelectionPresence $presence, private readonly ManageChoreDraw $chores, private readonly ManageMovieChoice $movies) {}

    public function store(Request $request, Couple $couple, string $kind, int $selection): JsonResponse
    {
        Gate::authorize('update', $couple);
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $current = $kind === 'chores' ? ChoreDraw::query()->findOrFail($selection) : MovieChoice::query()->findOrFail($selection);
        abort_unless($current->couple_id === $couple->id && in_array($actor->id, [$current->first_user_id, $current->second_user_id], true), Response::HTTP_NOT_FOUND);
        $this->presence->touch($actor, $current);
        if ($current->first_ready && $current->second_ready && $this->presence->areBothPresent($current)) {
            if ($current instanceof ChoreDraw && $current->status === SelectionStatus::Waiting) {
                $this->chores->start($actor, $couple, $current);
            }
            if ($current instanceof MovieChoice && $current->mode === 'roulette' && $current->status === SelectionStatus::Active) {
                $this->movies->draw($actor, $couple, $current);
            }
        }
        $current->refresh();

        return response()->json((new SelectionStateResource($current))->resolve($request));
    }
}
