<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Couples\EndCouple;
use App\Http\Requests\EndCoupleRequest;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class CoupleEndController extends Controller
{
    public function __construct(private readonly EndCouple $endCouple) {}

    public function create(Couple $couple): View
    {
        Gate::authorize('update', $couple);

        return view('couples.end', ['couple' => $couple]);
    }

    public function store(EndCoupleRequest $request, Couple $couple): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->endCouple->execute($actor, $couple);

        return redirect()->route('couples.index')->with('status', __('Пара завершена. История сохранена для прежних участников.'));
    }
}
