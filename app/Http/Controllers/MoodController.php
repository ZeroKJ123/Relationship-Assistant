<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Mood\AddMoodEmoji;
use App\Actions\Mood\RecordMood;
use App\Actions\Mood\SetMoodSchedule;
use App\Enums\MoodLevel;
use App\Http\Requests\MoodEmojiRequest;
use App\Http\Requests\SetMoodScheduleRequest;
use App\Http\Requests\StoreMoodRequest;
use App\Models\Couple;
use App\Models\MoodEntry;
use App\Models\MoodPrompt;
use App\Models\User;
use App\Services\Mood\MoodCalendar;
use App\Services\Mood\MoodHistory;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class MoodController extends Controller
{
    private const PAGE_SIZE = 20;

    public function __construct(private readonly RecordMood $record, private readonly SetMoodSchedule $schedule, private readonly MoodHistory $history, private readonly AddMoodEmoji $addEmoji, private readonly MoodCalendar $calendar) {}

    public function index(Request $request, Couple $couple): View
    {
        Gate::authorize('view', $couple);
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $member = $couple->members()->where('user_id', $actor->id)->firstOrFail();
        $prompt = $request->filled('prompt_id') ? MoodPrompt::query()->where('couple_id', $couple->id)->where('user_id', $actor->id)->whereNull('answered_at')->where('expires_at', '>', now())->findOrFail($request->integer('prompt_id')) : $this->history->getPendingPrompt($actor, $couple);

        $emojiEntry = $request->filled('emoji_entry') ? MoodEntry::query()->where('couple_id', $couple->id)->where('user_id', $actor->id)->whereNull('emoji')->whereNull('sticker_status')->find($request->integer('emoji_entry')) : null;

        return view('mood.index', ['calendar' => $this->calendar->getMonth($couple, $actor, CarbonImmutable::now()), 'emojiEntry' => $emojiEntry, 'emojis' => AddMoodEmoji::EMOJIS, 'couple' => $couple, 'member' => $member, 'prompt' => $prompt, 'latestMoods' => $this->history->getLatest($couple), 'levels' => MoodLevel::cases(), 'operationKey' => Str::uuid()->toString(), 'entries' => MoodEntry::query()->where('couple_id', $couple->id)->with('user:id,name')->latest('id')->paginate(self::PAGE_SIZE)]);
    }

    public function store(StoreMoodRequest $request, Couple $couple): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $entry = $this->record->execute($actor, $couple, $request->getData());

        return redirect()->route('mood.index', [$couple, 'emoji_entry' => $entry->id])->with('status', __('Настроение сохранено и видно партнёру.'));
    }

    public function emoji(MoodEmojiRequest $request, Couple $couple, MoodEntry $moodEntry): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->addEmoji->execute($actor, $couple, $moodEntry, $request->string('emoji')->toString());

        return redirect()->route('mood.index', $couple)->with('status', __('Эмоджи добавлено к настроению.'));
    }

    public function schedule(SetMoodScheduleRequest $request, Couple $couple): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->schedule->execute($actor, $couple, $request->boolean('enabled'), $request->integer('expected_version'));

        return redirect()->route('mood.index', $couple)->with('status', __('Настройка расписания сохранена.'));
    }
}
