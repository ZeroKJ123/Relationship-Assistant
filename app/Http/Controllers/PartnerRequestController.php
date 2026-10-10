<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Requests\CreatePartnerRequest;
use App\Enums\ConditionType;
use App\Enums\Currency;
use App\Enums\RequestType;
use App\Http\Requests\StorePartnerRequest;
use App\Models\Couple;
use App\Models\Dish;
use App\Models\ExpenseCategory;
use App\Models\PartnerRequest;
use App\Models\RequestTemplate;
use App\Models\SettlementMethod;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class PartnerRequestController extends Controller
{
    private const PAGE_SIZE = 10;

    public function __construct(private readonly CreatePartnerRequest $createRequest) {}

    public function index(Couple $couple): View
    {
        return view('requests.index', ['couple' => $couple, 'requests' => PartnerRequest::query()->where('couple_id', $couple->id)->with(['author:id,name', 'recipient:id,name'])->latest('id')->paginate(self::PAGE_SIZE)]);
    }

    public function create(Request $request, Couple $couple): View|RedirectResponse
    {
        Gate::authorize('update', $couple);

        $dish = $request->filled('dish_id') ? Dish::query()->findOrFail($request->integer('dish_id')) : null;
        if ($dish !== null) {
            Gate::authorize('view', $dish);
            abort_unless($dish->user_id !== auth()->id() && $couple->members()->where('active_user_id', $dish->user_id)->exists(), Response::HTTP_FORBIDDEN);
        }
        $preset = RequestTemplate::query()->whereKey($request->integer('request_template_id'))->first();
        if ($preset?->type === RequestType::Money) {
            return redirect()->route('money.create', [$couple, 'request_template_id' => $preset->id]);
        }
        if ($preset?->type === RequestType::Guests) {
            return redirect()->route('guests.create', [$couple, 'request_template_id' => $preset->id]);
        }
        if ($preset?->type === RequestType::Event) {
            return redirect()->route('dates.create', [$couple, 'request_template_id' => $preset->id]);
        }

        return view('requests.create', ['dish' => $dish, 'preset' => $preset, 'templates' => RequestTemplate::query()->where('type', '!=', RequestType::Money)->with('category')->orderBy('sort_order')->get(), 'expenses' => ExpenseCategory::query()->orderBy('sort_order')->get(), 'couple' => $couple, 'types' => array_filter(RequestType::cases(), fn (RequestType $type): bool => $type !== RequestType::Money), 'selectedType' => $request->query('type') === 'rating' ? 'rating' : null, 'operationKey' => Str::uuid()->toString()]);
    }

    public function store(StorePartnerRequest $request, Couple $couple): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $created = $this->createRequest->execute($actor, $couple, $request->getData($couple));

        return redirect()->route('requests.show', [$couple, $created])->with('status', __('Просьба отправлена партнёру.'));
    }

    public function show(Couple $couple, PartnerRequest $partnerRequest): View
    {
        abort_unless($partnerRequest->couple_id === $couple->id, Response::HTTP_NOT_FOUND);
        Gate::authorize('view', $partnerRequest);
        $partnerRequest->load(['author:id,name', 'recipient:id,name', 'moneyRequest', 'guestVisit', 'dateInvitation']);

        return view('requests.show', ['wishes' => WishlistItem::query()->where('user_id', auth()->id())->whereNull('archived_at')->whereNull('claimed_condition_id')->latest('id')->paginate(self::PAGE_SIZE, ['*'], 'wishes_page')->withQueryString(), 'currencies' => Currency::cases(), 'conditionTypes' => ConditionType::cases(), 'methods' => SettlementMethod::query()->whereIn('kind', ['action', 'kisses', 'sushi', 'custom', 'money', 'wishlist', 'fuel'])->where(fn (Builder $query) => $query->whereNull('user_id')->orWhere('user_id', auth()->id()))->orderBy('sort_order')->get(), 'couple' => $couple, 'partnerRequest' => $partnerRequest, 'conditions' => $partnerRequest->conditions()->latest('version')->paginate(self::PAGE_SIZE), 'currentCondition' => $partnerRequest->conditions()->latest('version')->first()]);
    }
}
