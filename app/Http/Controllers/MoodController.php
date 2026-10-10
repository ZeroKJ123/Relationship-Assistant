<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Mood\RecordMood;
use App\Actions\Mood\SetMoodSchedule;
use App\Enums\MoodLevel;
use App\Http\Requests\SetMoodScheduleRequest;
use App\Http\Requests\StoreMoodRequest;
use App\Models\Couple;
use App\Models\MoodEntry;
use App\Models\MoodPrompt;
use App\Models\User;
use App\Services\Mood\MoodHistory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class MoodController extends Controller
{
    private const PAGE_SIZE = 20;

    public function __construct(private readonly RecordMood $record, private readonly SetMoodSchedule $schedule, private readonly MoodHistory $history) {}

    public function index(Request $request, Couple $couple): View
    {
        Gate::authorize('view', $couple);
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $member = $couple->members()->where('user_id', $actor->id)->firstOrFail();
        $prompt = $request->filled('prompt_id') ? MoodPrompt::query()->where('couple_id', $couple->id)->where('user_id', $actor->id)->whereNull('answered_at')->where('expires_at', '>', now())->findOrFail($request->integer('prompt_id')) : null;

        return view('mood.index', ['couple' => $couple, 'member' => $member, 'prompt' => $prompt, 'latestMoods' => $this->history->getLatest($couple), 'levels' => MoodLevel::cases(), 'operationKey' => Str::uuid()->toString(), 'entries' => MoodEntry::query()->where('couple_id', $couple->id)->where('recorded_at', '>=', now()->subDays(MoodEntry::HISTORY_DAYS))->with('user:id,name')->latest('id')->paginate(self::PAGE_SIZE)]);
    }

    public function store(StoreMoodRequest $request, Couple $couple): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->record->execute($actor, $couple, $request->getData());

        return redirect()->route('mood.index', $couple)->with('status', __('Настроение сохранено и видно партнёру.'));
    }

    public function schedule(SetMoodScheduleRequest $request, Couple $couple): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->schedule->execute($actor, $couple, $request->boolean('enabled'), $request->integer('expected_version'));

        return redirect()->route('mood.index', $couple)->with('status', __('Настройка расписания сохранена.'));
    }
}
