<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Couples\Agreements\SubmitAnswer;
use App\Enums\CoupleStatus;
use App\Http\Requests\WeeklyLimitRequest;
use App\Models\Couple;
use App\Models\User;
use App\Services\Couples\WeeklyLimit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CoupleAgreementController extends Controller
{
    private const HISTORY_PAGE_SIZE = 10;

    public function __construct(private readonly SubmitAnswer $submitAnswer) {}

    public function show(Request $request, Couple $couple): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $agreement = $couple->agreement()->with('answers.user:id,name')->first();
        $proposal = $agreement === null ? null : $agreement->proposals()->with('approvals')->where('version', $agreement->current_version)->first();
        $history = $agreement?->proposals()->with('author:id,name')->latest('version')->paginate(self::HISTORY_PAGE_SIZE);
        $ownAnswer = $agreement?->answers->firstWhere('user_id', $actor->id);

        return view('couples.agreement', [
            'couple' => $couple, 'agreement' => $agreement, 'proposal' => $proposal,
            'history' => $history, 'ownAnswer' => $ownAnswer,
            'isEditable' => $couple->status === CoupleStatus::Active,
            'hasApproved' => $proposal?->approvals->contains('user_id', $actor->id) ?? false,
            'minimum' => WeeklyLimit::MINIMUM, 'maximum' => WeeklyLimit::MAXIMUM,
        ]);
    }

    public function store(WeeklyLimitRequest $request, Couple $couple): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->submitAnswer->execute($actor, $couple, $request->getData());

        return redirect()->route('agreements.show', $couple)->with('status', __('Ваш ответ сохранён.'));
    }
}
