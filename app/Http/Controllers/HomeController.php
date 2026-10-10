<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Couple;
use App\Models\User;
use App\Services\Mood\MoodHistory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class HomeController extends Controller
{
    public function __construct(private readonly MoodHistory $moods) {}

    public function __invoke(Request $request): View
    {
        $actor = $request->user();
        $couple = $actor instanceof User ? Couple::query()->whereRelation('members', 'active_user_id', $actor->id)->first() : null;

        return view('home', ['currentCouple' => $couple, 'latestMoods' => $couple === null ? collect() : $this->moods->getLatest($couple)]);
    }
}
