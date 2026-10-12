<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Couple;
use App\Models\User;
use App\Services\Mood\MoodCalendar;
use App\Services\Mood\MoodDay;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class MoodDayController extends Controller
{
    public function __construct(private readonly MoodDay $days, private readonly MoodCalendar $calendar) {}

    public function __invoke(Request $request, Couple $couple): View
    {
        Gate::authorize('view', $couple);
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $data = $request->validate(['date' => ['sometimes', 'required', 'date_format:Y-m-d']]);
        $date = $data['date'] ?? CarbonImmutable::now($couple->timezone)->format('Y-m-d');
        $member = $couple->members()->where('user_id', '!=', $actor->id)->firstOrFail();
        $partner = User::query()->findOrFail($member->user_id);

        $summary = $this->days->getSummary($couple, $partner->id, $date);

        return view('mood.day', ['dateTitle' => CarbonImmutable::parse($date, $couple->timezone)->isoFormat('D MMMM YYYY'), 'calendar' => $this->calendar->getMonth($couple, $partner, CarbonImmutable::now(), $date), 'couple' => $couple, 'partner' => $partner, 'date' => $date, 'summary' => $summary, 'chart' => $this->days->getChart($couple, $partner->id, $date, $summary['average']), 'entries' => $this->days->getEntries($couple, $partner->id, $date)->orderByDesc('recorded_at')->orderByDesc('id')->paginate(MoodDay::PAGE_SIZE)->withQueryString()]);
    }
}
