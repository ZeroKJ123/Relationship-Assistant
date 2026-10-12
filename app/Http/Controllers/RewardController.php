<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Rewards\ArchiveReward;
use App\Actions\Rewards\SaveReward;
use App\Http\Requests\ArchiveRewardRequest;
use App\Http\Requests\SaveRewardRequest;
use App\Models\CoupleMember;
use App\Models\Reward;
use App\Models\User;
use App\Services\Catalogs\OwnedCatalogAccess;
use App\Services\Hearts\WalletSnapshot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class RewardController extends Controller
{
    private const PAGE_SIZE = 20;

    public function __construct(private readonly SaveReward $save, private readonly ArchiveReward $archive, private readonly OwnedCatalogAccess $access, private readonly WalletSnapshot $wallets) {}

    public function index(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $owner = User::query()->findOrFail($request->integer('owner_id', $actor->id));
        abort_unless($this->access->canView($actor, $owner->id), Response::HTTP_FORBIDDEN);
        $membership = CoupleMember::query()->with('couple')->where('active_user_id', $actor->id)->first();
        $partner = $membership === null ? null : CoupleMember::query()->with('user')->where('couple_id', $membership->couple_id)->whereNotNull('active_user_id')->where('user_id', '!=', $actor->id)->first()?->user;

        return view('rewards.index', ['rewards' => Reward::query()->where('user_id', $owner->id)->whereNull('archived_at')->orderBy('heart_price')->orderBy('id')->paginate(self::PAGE_SIZE)->withQueryString(), 'owner' => $owner, 'partner' => $partner, 'couple' => $membership?->couple, 'isOwn' => $owner->id === $actor->id, 'wallet' => $this->wallets->get($actor)]);
    }

    public function create(): View
    {
        return view('rewards.form', ['reward' => null, 'operationKey' => Str::uuid()->toString()]);
    }

    public function edit(Reward $reward): View
    {
        Gate::authorize('update', $reward);

        return view('rewards.form', ['reward' => $reward, 'operationKey' => Str::uuid()->toString()]);
    }

    public function store(SaveRewardRequest $request): RedirectResponse
    {
        return $this->persist($request);
    }

    public function update(SaveRewardRequest $request, Reward $reward): RedirectResponse
    {
        return $this->persist($request, $reward);
    }

    public function destroy(ArchiveRewardRequest $request, Reward $reward): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->archive->execute($actor, $reward, $request->integer('expected_version'));

        return redirect()->route('rewards.index')->with('status', __('Награда убрана из магазина. Уже созданные просьбы сохранены.'));
    }

    private function persist(SaveRewardRequest $request, ?Reward $reward = null): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->save->execute($actor, $request->getData(), $reward);

        return redirect()->route('rewards.index')->with('status', __('Награда сохранена.'));
    }
}
