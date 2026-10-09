<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Couples\Agreements\AcceptProposal;
use App\Actions\Couples\Agreements\DeclineProposal;
use App\Http\Requests\ConfirmAgreementRequest;
use App\Models\AgreementProposal;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class AgreementApprovalController extends Controller
{
    public function __construct(private readonly AcceptProposal $acceptProposal, private readonly DeclineProposal $declineProposal) {}

    public function store(ConfirmAgreementRequest $request, Couple $couple, AgreementProposal $proposal): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->acceptProposal->execute($actor, $couple, $proposal);

        return redirect()->route('agreements.show', $couple)->with('status', __('Ваше согласие сохранено. Правило действует после двух согласий.'));
    }

    public function destroy(ConfirmAgreementRequest $request, Couple $couple, AgreementProposal $proposal): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->declineProposal->execute($actor, $couple, $proposal);

        return redirect()->route('agreements.show', $couple)->with('status', __('Вариант отклонён. Можно предложить другой.'));
    }
}
