<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Couples\Agreements\ProposeWeeklyLimit;
use App\Http\Requests\WeeklyLimitRequest;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class AgreementProposalController extends Controller
{
    public function __construct(private readonly ProposeWeeklyLimit $proposeWeeklyLimit) {}

    public function store(WeeklyLimitRequest $request, Couple $couple): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->proposeWeeklyLimit->execute($actor, $couple, $request->getData());

        return redirect()->route('agreements.show', $couple)->with('status', __('Новый вариант предложен. Каждый партнёр подтверждает его отдельно.'));
    }
}
