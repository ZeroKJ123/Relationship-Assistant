<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Dates\CreateDateInvitation;
use App\Enums\InvitationKind;
use App\Enums\RequestType;
use App\Http\Requests\StoreDateInvitationRequest;
use App\Models\Couple;
use App\Models\DateIdea;
use App\Models\RequestTemplate;
use App\Models\User;
use App\Services\Requests\InvitationTime;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class DateInvitationController extends Controller
{
    public function __construct(private readonly CreateDateInvitation $create, private readonly InvitationTime $time) {}

    public function create(Request $request, Couple $couple): View
    {
        Gate::authorize('update', $couple);
        $preset = $request->filled('request_template_id') ? RequestTemplate::query()->where('type', RequestType::Event)->findOrFail($request->integer('request_template_id')) : null;

        return view('dates.create', ['ideaTitle' => $request->filled('idea_id') ? DateIdea::query()->where('couple_id', $couple->id)->whereKey($request->integer('idea_id'))->value('title') : null, 'couple' => $couple, 'preset' => $preset, 'kinds' => InvitationKind::cases(), 'operationKey' => Str::uuid()->toString()]);
    }

    public function store(StoreDateInvitationRequest $request, Couple $couple): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $created = $this->create->execute($actor, $couple, $request->getData($couple, $this->time));

        return redirect()->route('requests.show', [$couple, $created])->with('status', __('Приглашение отправлено партнёру.'));
    }
}
