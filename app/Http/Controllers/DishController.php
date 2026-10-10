<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Dishes\SaveDish;
use App\Enums\Currency;
use App\Enums\DishKind;
use App\Http\Requests\SaveDishRequest;
use App\Models\CoupleMember;
use App\Models\Dish;
use App\Models\User;
use App\Services\Catalogs\OwnedCatalogAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class DishController extends Controller
{
    private const PAGE_SIZE = 12;

    public function __construct(private readonly SaveDish $save, private readonly OwnedCatalogAccess $access) {}

    public function index(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $owner = User::query()->findOrFail($request->integer('owner_id', $actor->id));
        abort_unless($this->access->canView($actor, $owner->id), Response::HTTP_FORBIDDEN);
        $membership = CoupleMember::query()->with('couple')->where('active_user_id', $actor->id)->first();
        $partner = $membership === null ? null : CoupleMember::query()->with('user')->where('couple_id', $membership->couple_id)->whereNotNull('active_user_id')->where('user_id', '!=', $actor->id)->first()?->user;
        $query = Dish::query()->where('user_id', $owner->id);
        $filter = DishKind::tryFrom($request->string('kind')->toString());
        if ($filter !== null) {
            $query->where('kind', $filter);
        }

        return view('dishes.index', ['items' => $query->latest('id')->paginate(self::PAGE_SIZE)->withQueryString(), 'owner' => $owner, 'partner' => $partner, 'couple' => $membership?->couple, 'isOwn' => $owner->id === $actor->id, 'kinds' => DishKind::cases(), 'filter' => $filter]);
    }

    public function create(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);

        return view('dishes.form', ['defaultCurrency' => $this->access->getCurrency($actor), 'item' => null, 'currencies' => Currency::cases(), 'operationKey' => Str::uuid()->toString(), 'kinds' => DishKind::cases()]);
    }

    public function edit(Request $request, Dish $dish): View
    {
        Gate::authorize('update', $dish);

        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);

        return view('dishes.form', ['defaultCurrency' => $this->access->getCurrency($actor), 'item' => $dish, 'currencies' => Currency::cases(), 'operationKey' => Str::uuid()->toString(), 'kinds' => DishKind::cases()]);
    }

    public function store(SaveDishRequest $request): RedirectResponse
    {
        return $this->persist($request);
    }

    public function update(SaveDishRequest $request, Dish $dish): RedirectResponse
    {
        return $this->persist($request, $dish);
    }

    private function persist(SaveDishRequest $request, ?Dish $item = null): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->save->execute($actor, $request->getData(), $item);

        return redirect()->route('dishes.index')->with('status', __('Карточка сохранена.'));
    }
}
