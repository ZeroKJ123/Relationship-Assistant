<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Couples\DeleteMemorableDate;
use App\Actions\Couples\SaveMemorableDate;
use App\Http\Requests\DeleteMemorableDateRequest;
use App\Http\Requests\StoreMemorableDateRequest;
use App\Models\Couple;
use App\Models\MemorableDate;
use App\Models\User;
use App\Services\Couples\MemorableDates;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class MemorableDateController extends Controller
{
    public function __construct(private readonly MemorableDates $dates, private readonly SaveMemorableDate $save, private readonly DeleteMemorableDate $delete) {}

    public function index(Couple $couple): View
    {
        Gate::authorize('view', $couple);

        return view('memorable-dates.index', ['couple' => $couple, 'dates' => $this->dates->getUpcoming($couple, CarbonImmutable::now()), 'canEdit' => Gate::allows('update', $couple), 'operationKey' => Str::uuid()->toString()]);
    }

    public function store(StoreMemorableDateRequest $request, Couple $couple): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->save->execute($actor, $couple, $request->getData());

        return redirect()->route('memorable_dates.index', $couple)->with('status', __('Дата сохранена. Напомним за три дня и в сам день.'));
    }

    public function destroy(DeleteMemorableDateRequest $request, Couple $couple, MemorableDate $memorableDate): RedirectResponse
    {
        abort_unless($memorableDate->couple_id === $couple->id, Response::HTTP_NOT_FOUND);
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->delete->execute($actor, $couple, $memorableDate);

        return redirect()->route('memorable_dates.index', $couple)->with('status', __('Дата удалена.'));
    }
}
