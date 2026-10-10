<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Wishlist\ArchiveWishlistItem;
use App\Actions\Wishlist\SaveWishlistItem;
use App\Enums\Currency;
use App\Http\Requests\ArchiveWishlistItemRequest;
use App\Http\Requests\SaveWishlistItemRequest;
use App\Models\CoupleMember;
use App\Models\User;
use App\Models\WishlistItem;
use App\Services\Catalogs\OwnedCatalogAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class WishlistItemController extends Controller
{
    private const PAGE_SIZE = 12;

    public function __construct(private readonly SaveWishlistItem $save, private readonly OwnedCatalogAccess $access, private readonly ArchiveWishlistItem $archive) {}

    public function index(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $owner = User::query()->findOrFail($request->integer('owner_id', $actor->id));
        abort_unless($this->access->canView($actor, $owner->id), Response::HTTP_FORBIDDEN);
        $membership = CoupleMember::query()->with('couple')->where('active_user_id', $actor->id)->first();
        $partner = $membership === null ? null : CoupleMember::query()->with('user')->where('couple_id', $membership->couple_id)->whereNotNull('active_user_id')->where('user_id', '!=', $actor->id)->first()?->user;
        $query = WishlistItem::query()->where('user_id', $owner->id);
        $isArchive = $request->boolean('archive');
        if ($isArchive) {
            $query->whereNotNull('archived_at');
        }
        if (! $isArchive) {
            $query->whereNull('archived_at');
        }

        return view('wishlist.index', ['items' => $query->latest('id')->paginate(self::PAGE_SIZE)->withQueryString(), 'owner' => $owner, 'partner' => $partner, 'couple' => $membership?->couple, 'isOwn' => $owner->id === $actor->id, 'isArchive' => $isArchive]);
    }

    public function create(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);

        return view('wishlist.form', ['defaultCurrency' => $this->access->getCurrency($actor), 'item' => null, 'currencies' => Currency::cases(), 'operationKey' => Str::uuid()->toString()]);
    }

    public function edit(Request $request, WishlistItem $wishlistItem): View
    {
        Gate::authorize('update', $wishlistItem);

        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);

        return view('wishlist.form', ['defaultCurrency' => $this->access->getCurrency($actor), 'item' => $wishlistItem, 'currencies' => Currency::cases(), 'operationKey' => Str::uuid()->toString()]);
    }

    public function store(SaveWishlistItemRequest $request): RedirectResponse
    {
        return $this->persist($request);
    }

    public function update(SaveWishlistItemRequest $request, WishlistItem $wishlistItem): RedirectResponse
    {
        return $this->persist($request, $wishlistItem);
    }

    public function archive(ArchiveWishlistItemRequest $request, WishlistItem $wishlistItem): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->archive->execute($actor, $wishlistItem, $request->integer('expected_version'));

        return redirect()->route('wishlist.index')->with('status', __('Желание отправлено в архив.'));
    }

    private function persist(SaveWishlistItemRequest $request, ?WishlistItem $item = null): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->save->execute($actor, $request->getData(), $item);

        return redirect()->route('wishlist.index')->with('status', __('Карточка сохранена.'));
    }
}
