<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Requests\CancelPartnerRequest;
use App\Http\Requests\ConfirmRequestCancellationRequest;
use App\Models\Couple;
use App\Models\PartnerRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class RequestCancellationController extends Controller
{
    public function __construct(private readonly CancelPartnerRequest $cancel) {}

    public function __invoke(ConfirmRequestCancellationRequest $request, Couple $couple, PartnerRequest $partnerRequest): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->cancel->execute($actor, $couple, $partnerRequest, $request->integer('expected_version'));

        return redirect()->route('requests.show', [$couple, $partnerRequest])->with('status', __('Ответ об отмене сохранён. Принятая просьба отменяется после согласия обоих.'));
    }
}
