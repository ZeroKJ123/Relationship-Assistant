<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Duties\ReportDuty;
use App\Actions\Duties\VerifyDuty;
use App\Enums\DutyFilter;
use App\Enums\DutyKind;
use App\Http\Requests\ConfirmDutyRequest;
use App\Models\Couple;
use App\Models\Duty;
use App\Models\PartnerRequest;
use App\Models\RequestCondition;
use App\Models\User;
use App\ViewModels\DutyGroups;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class DutyController extends Controller
{
    private const PAGE_SIZE = 10;

    public function __construct(private readonly ReportDuty $report, private readonly VerifyDuty $verify) {}

    public function index(Request $request, Couple $couple): View
    {
        $filter = $request->enum('filter', DutyFilter::class) ?? DutyFilter::Active;
        Log::debug('duties.list_started', ['couple_id' => $couple->id, 'filter' => $filter->value]);
        $duties = Duty::query()->where('couple_id', $couple->id)->whereIn('status', $filter->statuses())->with(['executor:id,name', 'reviewer:id,name'])->orderBy('due_at')->orderBy('id')->paginate(self::PAGE_SIZE)->withQueryString();
        Log::debug('duties.list_loaded', ['couple_id' => $couple->id, 'filter' => $filter->value, 'count' => $duties->count()]);

        return view('duties.index', ['couple' => $couple, 'duties' => $duties, 'groups' => (new DutyGroups)->group($duties->getCollection(), CarbonImmutable::now()), 'filters' => DutyFilter::cases(), 'filter' => $filter]);
    }

    public function show(Couple $couple, Duty $duty): View
    {
        abort_unless($duty->couple_id === $couple->id, Response::HTTP_NOT_FOUND);
        Gate::authorize('view', $duty);
        $duty->load(['executor:id,name', 'reviewer:id,name']);

        $type = $duty->kind === DutyKind::Service ? PartnerRequest::query()->findOrFail($duty->partner_request_id)->type->value : RequestCondition::query()->findOrFail($duty->request_condition_id)->type->value;
        $reportLabel = match ($type) {
            'money' => __('Оплата отправлена'),'gift' => __('Подарок передан'),default => __('Сделано')
        };
        $verifyLabel = match ($type) {
            'money' => __('Деньги получены'),'gift' => __('Подарок получен'),default => __('Подтвердить выполнение')
        };

        return view('duties.show', ['reportLabel' => $reportLabel, 'verifyLabel' => $verifyLabel, 'couple' => $couple, 'duty' => $duty, 'history' => $duty->confirmations()->latest('version')->paginate(self::PAGE_SIZE)]);
    }

    public function report(ConfirmDutyRequest $request, Couple $couple, Duty $duty): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $data = $request->getData();
        $this->report->execute($actor, $couple, $duty, version: $data->expectedVersion, isDone: $data->answer);

        return redirect()->route($request->boolean('return_request') ? 'requests.show' : 'duties.show', [$couple, $request->boolean('return_request') ? $duty->partner_request_id : $duty])->with('status', __('Ваш ответ о выполнении сохранён.'));
    }

    public function verify(ConfirmDutyRequest $request, Couple $couple, Duty $duty): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $data = $request->getData();
        $this->verify->execute($actor, $couple, $duty, version: $data->expectedVersion, isConfirmed: $data->answer);

        return redirect()->route($request->boolean('return_request') ? 'requests.show' : 'duties.show', [$couple, $request->boolean('return_request') ? $duty->partner_request_id : $duty])->with('status', __('Результат проверки сохранён.'));
    }
}
