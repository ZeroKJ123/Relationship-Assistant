<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Requests\CreateGuestVisit;
use App\Enums\RequestType;
use App\Http\Requests\StoreGuestVisitRequest;
use App\Models\Couple;
use App\Models\Friend;
use App\Models\GuestVisit;
use App\Models\RequestTemplate;
use App\Models\User;
use App\Services\Requests\InvitationTime;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class GuestVisitController extends Controller
{
    private const PAGE_SIZE = 20;

    public function __construct(private readonly CreateGuestVisit $create, private readonly InvitationTime $time) {}

    public function create(Request $request, Couple $couple): View
    {
        Gate::authorize('update', $couple);
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $validated = Validator::make(['friends' => $request->old('friends', $request->input('friends', []))], ['friends' => ['array', 'list', sprintf('max:%d', GuestVisit::MAXIMUM_GUESTS)], 'friends.*' => ['string', 'distinct:strict', 'regex:/\A[1-9][0-9]*:[0-9]+\z/D']])->validate();
        /** @var list<string> $selected */
        $selected = $validated['friends'] ?? [];
        $selectedChoices = collect($selected)->keyBy(fn (string $choice): int => (int) explode(':', $choice)[0])->all();
        $selectedIds = array_map(fn (string $choice): int => (int) explode(':', $choice)[0], $selected);
        $preset = $request->filled('request_template_id') ? RequestTemplate::query()->where('type', RequestType::Guests)->findOrFail($request->integer('request_template_id')) : null;

        return view('guests.create', ['couple' => $couple, 'preset' => $preset, 'friends' => Friend::query()->where('user_id', $actor->id)->whereNull('archived_at')->latest('id')->paginate(self::PAGE_SIZE), 'selected' => $selected, 'selectedChoices' => $selectedChoices, 'selectedFriends' => Friend::query()->where('user_id', $actor->id)->whereNull('archived_at')->whereIn('id', $selectedIds)->get(), 'values' => ['starts_at' => $request->string('starts_at')->toString(), 'duration_minutes' => $request->integer('duration_minutes', 60), 'reason' => $request->string('reason', $preset->name ?? '')->toString()], 'operationKey' => $request->filled('operation_key') ? $request->string('operation_key')->toString() : Str::uuid()->toString()]);
    }

    public function store(StoreGuestVisitRequest $request, Couple $couple): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $created = $this->create->execute($actor, $couple, $request->getData($couple, $this->time));

        return redirect()->route('requests.show', [$couple, $created])->with('status', __('Просьба о гостях отправлена.'));
    }
}
