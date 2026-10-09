<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Couples\AcceptInvitation;
use App\Actions\Couples\CancelInvitation;
use App\Actions\Couples\InvitePartner;
use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Requests\InvitePartnerRequest;
use App\Models\Couple;
use App\Models\CoupleInvitation;
use App\Models\User;
use App\Services\Couples\InvitationAvailability;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;

final class CoupleInvitationController extends Controller
{
    public function __construct(private readonly InvitePartner $invitePartner, private readonly AcceptInvitation $acceptInvitation, private readonly CancelInvitation $cancelInvitation, private readonly InvitationAvailability $availability) {}

    public function create(): View
    {
        return view('couples.create', ['defaultTimezone' => Couple::DEFAULT_TIMEZONE]);
    }

    public function store(InvitePartnerRequest $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $result = $this->invitePartner->execute($actor, $request->getData());
        $request->session()->flash('invitation_link_couple', $result->coupleId);
        $request->session()->flash('invitation_link', Crypt::encryptString(route('invitations.show', ['token' => $result->token])));

        return redirect()->route('couples.show', $result->coupleId);
    }

    public function show(Request $request, string $token): View
    {
        $invitation = CoupleInvitation::query()->where('token_hash', hash('sha256', $token))->with(['couple', 'inviter:id,name'])->firstOrFail();
        $couple = $invitation->couple;
        abort_unless($couple instanceof Couple, Response::HTTP_NOT_FOUND);
        $available = $this->availability->isAvailable($invitation, $couple);

        return view('couples.invitation', ['invitation' => $invitation, 'available' => $available, 'token' => $token, 'isOwn' => $request->user()?->getAuthIdentifier() === $invitation->inviter_id]);
    }

    public function update(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $couple = $this->acceptInvitation->execute($actor, $token);
        $request->session()->forget('url.intended');

        return redirect()->route('couples.show', $couple)->with('status', __('Теперь вы — пара!'));
    }

    public function destroy(Request $request, CoupleInvitation $invitation): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->cancelInvitation->execute($actor, $invitation);

        return redirect()->route('couples.show', $invitation->couple_id)->with('status', __('Приглашение отменено.'));
    }
}
