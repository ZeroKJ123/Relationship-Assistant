<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Friends\ArchiveFriend;
use App\Actions\Friends\SaveFriend;
use App\Http\Requests\ArchiveFriendRequest;
use App\Http\Requests\SaveFriendRequest;
use App\Models\Friend;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class FriendController extends Controller
{
    private const PAGE_SIZE = 20;

    public function __construct(private readonly SaveFriend $save, private readonly ArchiveFriend $archive) {}

    public function index(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $isArchive = $request->boolean('archive');
        $query = Friend::query()->where('user_id', $actor->id);
        if ($isArchive) {
            $query->whereNotNull('archived_at');
        }
        if (! $isArchive) {
            $query->whereNull('archived_at');
        }

        return view('friends.index', ['friends' => $query->latest('id')->paginate(self::PAGE_SIZE)->withQueryString(), 'isArchive' => $isArchive]);
    }

    public function create(): View
    {
        return view('friends.form', ['friend' => null, 'operationKey' => Str::uuid()->toString()]);
    }

    public function edit(Friend $friend): View
    {
        Gate::authorize('update', $friend);

        return view('friends.form', ['friend' => $friend, 'operationKey' => Str::uuid()->toString()]);
    }

    public function store(SaveFriendRequest $request): RedirectResponse
    {
        return $this->persist($request);
    }

    public function update(SaveFriendRequest $request, Friend $friend): RedirectResponse
    {
        return $this->persist($request, $friend);
    }

    public function destroy(ArchiveFriendRequest $request, Friend $friend): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->archive->execute($actor, $friend, $request->integer('expected_version'));

        return redirect()->route('friends.index')->with('status', __('Друг перенесён в архив. Прежние приглашения сохранены.'));
    }

    private function persist(SaveFriendRequest $request, ?Friend $friend = null): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->save->execute($actor, $request->getData(), $friend);

        return redirect()->route('friends.index')->with('status', __('Друг сохранён.'));
    }
}
