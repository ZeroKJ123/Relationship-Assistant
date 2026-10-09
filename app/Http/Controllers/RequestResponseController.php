<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Requests\ResolveCondition;
use App\Actions\Requests\RespondToRequest;
use App\Http\Requests\AcceptRequestCondition;
use App\Http\Requests\RespondPartnerRequest;
use App\Models\Couple;
use App\Models\PartnerRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class RequestResponseController extends Controller
{
    public function __construct(private readonly RespondToRequest $respond, private readonly ResolveCondition $resolveCondition) {}

    public function store(RespondPartnerRequest $request, Couple $couple, PartnerRequest $partnerRequest): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->respond->execute($actor, $couple, $partnerRequest, $request->getData($couple));

        return redirect()->route('requests.show', [$couple, $partnerRequest])->with('status', __('Ответ сохранён.'));
    }

    public function condition(AcceptRequestCondition $request, Couple $couple, PartnerRequest $partnerRequest): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->resolveCondition->execute($actor, $couple, $partnerRequest, version: $request->integer('expected_version'), isAccepted: $request->string('decision')->toString() === 'accept');

        return redirect()->route('requests.show', [$couple, $partnerRequest])->with('status', __('Решение по условию сохранено.'));
    }
}
