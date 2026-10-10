<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reminders\CancelReminder;
use App\Actions\Reminders\CreateReminder;
use App\Http\Requests\CancelReminderRequest;
use App\Http\Requests\StoreReminderRequest;
use App\Models\Couple;
use App\Models\PartnerReminder;
use App\Models\User;
use App\Services\Requests\InvitationTime;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class PartnerReminderController extends Controller
{
    private const PAGE_SIZE = 20;

    public function __construct(private readonly CreateReminder $create, private readonly CancelReminder $cancel, private readonly InvitationTime $time) {}

    public function index(Couple $couple): View
    {
        Gate::authorize('view', $couple);

        return view('reminders.index', ['couple' => $couple, 'reminders' => PartnerReminder::query()->where('couple_id', $couple->id)->with(['author:id,name', 'recipient:id,name'])->latest('id')->paginate(self::PAGE_SIZE)]);
    }

    public function create(Couple $couple): View
    {
        Gate::authorize('update', $couple);

        return view('reminders.create', ['couple' => $couple, 'operationKey' => Str::uuid()->toString()]);
    }

    public function store(StoreReminderRequest $request, Couple $couple): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->create->execute($actor, $couple, $request->getData($couple, $this->time));

        return redirect()->route('reminders.index', $couple)->with('status', __('Напоминание запланировано.'));
    }

    public function destroy(CancelReminderRequest $request, Couple $couple, PartnerReminder $partnerReminder): RedirectResponse
    {
        abort_unless($partnerReminder->couple_id === $couple->id, Response::HTTP_NOT_FOUND);
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->cancel->execute($actor, $partnerReminder, $request->integer('expected_version'));

        return redirect()->route('reminders.index', $couple)->with('status', __('Напоминание отменено.'));
    }
}
