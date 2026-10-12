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
use App\Models\Reward;
use App\Models\SettlementMethod;
use App\Models\User;
use App\Models\WishlistItem;
use App\ViewModels\RequestProgress;
use Carbon\CarbonImmutable;
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

    private const DUE_FIELD_FORMAT = 'Y-m-d\\TH:i';

    private const EVENING_HOUR = 21;

    private const DAYS_IN_WEEK = 7;

    public function __construct(private readonly CreatePartnerRequest $createRequest) {}

    public function index(Request $request, Couple $couple): View
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User, Response::HTTP_UNAUTHORIZED);
        $progress = new RequestProgress;
        $requests = PartnerRequest::query()->where('couple_id', $couple->id)->with(['author:id,name', 'recipient:id,name', 'duties'])->latest('id')->paginate(self::PAGE_SIZE);

        return view('requests.index', ['progress' => $progress, 'viewer' => $viewer, 'couple' => $couple, 'requests' => $requests, 'groups' => $progress->group($requests->getCollection())]);
    }

    public function create(Request $request, Couple $couple): View|RedirectResponse
    {
        Gate::authorize('update', $couple);

        $dish = $request->filled('dish_id') ? Dish::query()->findOrFail($request->integer('dish_id')) : null;
        if ($dish !== null) {
            Gate::authorize('view', $dish);
            abort_unless($dish->user_id !== auth()->id() && $couple->members()->where('active_user_id', $dish->user_id)->exists(), Response::HTTP_FORBIDDEN);
        }
        $reward = $request->filled('reward_id') ? Reward::query()->findOrFail($request->integer('reward_id')) : null;
        if ($reward !== null) {
            Gate::authorize('view', $reward);
            abort_unless($reward->archived_at === null && $reward->user_id !== auth()->id() && $couple->members()->where('active_user_id', $reward->user_id)->exists(), Response::HTTP_FORBIDDEN);
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

        return view('requests.create', ['reward' => $reward, 'dish' => $dish, 'preset' => $preset, 'expenses' => ExpenseCategory::query()->orderBy('sort_order')->get(), 'couple' => $couple, 'selectedType' => in_array($request->query('type'), ['service', 'gift', 'food', 'rating'], true) ? $request->query('type') : RequestType::Service->value, 'defaultDueAt' => CarbonImmutable::now($couple->timezone)->addDay()->format(self::DUE_FIELD_FORMAT), 'dueShortcuts' => $this->getDueShortcuts(CarbonImmutable::now($couple->timezone)), 'operationKey' => Str::uuid()->toString()]);
    }

    /** @return array<string,string> подпись быстрой кнопки и значение поля срока */
    private function getDueShortcuts(CarbonImmutable $now): array
    {
        $evening = $now->setTime(self::EVENING_HOUR, 0);

        return [
            __('Сегодня') => ($evening->greaterThan($now) ? $evening : $now->endOfDay())->format(self::DUE_FIELD_FORMAT),
            __('Завтра') => $now->addDay()->format(self::DUE_FIELD_FORMAT),
            __('Через неделю') => $now->addDays(self::DAYS_IN_WEEK)->format(self::DUE_FIELD_FORMAT),
        ];
    }

    public function store(StorePartnerRequest $request, Couple $couple): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $created = $this->createRequest->execute($actor, $couple, $request->getData($couple));

        return redirect()->route('requests.show', [$couple, $created])->with('status', __('Просьба отправлена партнёру.'));
    }

    public function show(Request $request, Couple $couple, PartnerRequest $partnerRequest): View
    {
        abort_unless($partnerRequest->couple_id === $couple->id, Response::HTTP_NOT_FOUND);
        Gate::authorize('view', $partnerRequest);
        $partnerRequest->load(['author:id,name', 'recipient:id,name', 'moneyRequest', 'guestVisit', 'dateInvitation', 'duties.executor:id,name', 'duties.reviewer:id,name', 'duties.couple']);

        return view('requests.show', ['isAuthor' => $request->user()?->id === $partnerRequest->author_id, 'isRecipient' => $request->user()?->id === $partnerRequest->recipient_id, 'progress' => new RequestProgress, 'wishes' => WishlistItem::query()->where('user_id', auth()->id())->whereNull('archived_at')->whereNull('claimed_condition_id')->latest('id')->paginate(self::PAGE_SIZE, ['*'], 'wishes_page')->withQueryString(), 'currencies' => Currency::cases(), 'conditionTypes' => ConditionType::cases(), 'methods' => SettlementMethod::query()->whereIn('kind', ['action', 'kisses', 'sushi', 'custom', 'money', 'wishlist', 'fuel'])->where(fn (Builder $query) => $query->whereNull('user_id')->orWhere('user_id', auth()->id()))->orderBy('sort_order')->get(), 'couple' => $couple, 'partnerRequest' => $partnerRequest, 'conditions' => $partnerRequest->conditions()->latest('version')->paginate(self::PAGE_SIZE), 'currentCondition' => $partnerRequest->conditions()->latest('version')->first()]);
    }
}
