<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Duties\ReportDuty;
use App\Actions\Duties\VerifyDuty;
use App\Http\Requests\ConfirmDutyRequest;
use App\Models\Couple;
use App\Models\Duty;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class DutyController extends Controller
{
    private const PAGE_SIZE = 10;

    public function __construct(private readonly ReportDuty $report, private readonly VerifyDuty $verify) {}

    public function index(Couple $couple): View
    {
        return view('duties.index', ['couple' => $couple, 'duties' => Duty::query()->where('couple_id', $couple->id)->with(['executor:id,name', 'reviewer:id,name'])->latest('id')->paginate(self::PAGE_SIZE)]);
    }

    public function show(Couple $couple, Duty $duty): View
    {
        abort_unless($duty->couple_id === $couple->id, Response::HTTP_NOT_FOUND);
        Gate::authorize('view', $duty);
        $duty->load(['executor:id,name', 'reviewer:id,name']);

        return view('duties.show', ['couple' => $couple, 'duty' => $duty, 'history' => $duty->confirmations()->latest('version')->paginate(self::PAGE_SIZE)]);
    }

    public function report(ConfirmDutyRequest $request, Couple $couple, Duty $duty): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $data = $request->getData();
        $this->report->execute($actor, $couple, $duty, version: $data->expectedVersion, isDone: $data->answer);

        return redirect()->route('duties.show', [$couple, $duty])->with('status', __('Ваш ответ о выполнении сохранён.'));
    }

    public function verify(ConfirmDutyRequest $request, Couple $couple, Duty $duty): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $data = $request->getData();
        $this->verify->execute($actor, $couple, $duty, version: $data->expectedVersion, isConfirmed: $data->answer);

        return redirect()->route('duties.show', [$couple, $duty])->with('status', __('Результат проверки сохранён.'));
    }
}
