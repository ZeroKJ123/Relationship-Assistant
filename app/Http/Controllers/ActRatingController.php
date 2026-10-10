<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Requests\RatePartnerAct;
use App\Http\Requests\RatePartnerActRequest;
use App\Models\Couple;
use App\Models\PartnerRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class ActRatingController extends Controller
{
    public function __construct(private readonly RatePartnerAct $rate) {}

    public function store(RatePartnerActRequest $request, Couple $couple, PartnerRequest $partnerRequest): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->rate->execute($actor, $couple, $partnerRequest, $request->getData());

        return redirect()->route('requests.show', [$couple, $partnerRequest])->with('status', __('Оценка поступка отправлена партнёру.'));
    }
}
