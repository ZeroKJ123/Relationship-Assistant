<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Couples\RestoreStreak;
use App\Http\Requests\RestoreStreakRequest;
use App\Models\Couple;
use App\Models\User;
use App\Services\Couples\CoupleStreak;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class StreakController extends Controller
{
    public function __construct(private readonly CoupleStreak $streak, private readonly RestoreStreak $restore) {}

    public function show(Request $request, Couple $couple): View
    {
        Gate::authorize('view', $couple);
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);

        return view('streak.show', ['couple' => $couple, 'streak' => $this->streak->get($couple, $actor, CarbonImmutable::now()), 'levelDays' => CoupleStreak::LEVEL_DAYS, 'restoresPerMonth' => CoupleStreak::RESTORES_PER_MONTH, 'canEdit' => Gate::allows('update', $couple)]);
    }

    public function restore(RestoreStreakRequest $request, Couple $couple): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->restore->execute($actor, $couple);

        return redirect()->route('streak.show', $couple)->with('status', __('Серия восстановлена.'));
    }
}
