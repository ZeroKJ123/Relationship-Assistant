<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\InvitationStatus;
use App\Models\Couple;
use App\Models\User;
use App\Services\Couples\CoupleTimeline;
use App\Services\Mood\MoodHistory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;

final class CoupleController extends Controller
{
    private const HISTORY_PAGE_SIZE = 10;

    public function __construct(private readonly CoupleTimeline $timeline, private readonly MoodHistory $moods) {}

    public function index(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $current = $actor->coupleMemberships()->where('active_user_id', $actor->id)->first();
        $history = Couple::query()->whereRelation('members', 'user_id', $actor->id)->with('members.user:id,name')->latest('id')->paginate(self::HISTORY_PAGE_SIZE);

        return view('couples.index', ['current' => $current, 'history' => $history]);
    }

    public function show(Request $request, Couple $couple): View
    {
        $couple->load('members.user:id,name');
        $daysTogether = $this->timeline->getDaysTogether($couple);
        $encryptedLink = $request->session()->get('invitation_link');
        $link = $request->session()->get('invitation_link_couple') === $couple->id && is_string($encryptedLink) ? Crypt::decryptString($encryptedLink) : null;
        $invitation = $couple->invitations()->where('status', InvitationStatus::Pending->value)->where('expires_at', '>', now())->latest('id')->first();

        return view('couples.show', ['latestMoods' => $this->moods->getLatest($couple), 'couple' => $couple, 'daysTogether' => $daysTogether, 'invitationLink' => $link, 'invitation' => $invitation]);
    }
}
