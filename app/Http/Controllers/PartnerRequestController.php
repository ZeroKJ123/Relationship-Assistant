<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Requests\CreatePartnerRequest;
use App\Enums\RequestType;
use App\Http\Requests\StorePartnerRequest;
use App\Models\Couple;
use App\Models\ExpenseCategory;
use App\Models\PartnerRequest;
use App\Models\RequestTemplate;
use App\Models\SettlementMethod;
use App\Models\User;
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

    public function create(Request $request, Couple $couple): View
    {
        Gate::authorize('update', $couple);

        $preset = RequestTemplate::query()->whereKey($request->integer('request_template_id'))->first();

        return view('requests.create', ['preset' => $preset, 'templates' => RequestTemplate::query()->with('category')->orderBy('sort_order')->get(), 'expenses' => ExpenseCategory::query()->orderBy('sort_order')->get(), 'couple' => $couple, 'types' => RequestType::cases(), 'operationKey' => Str::uuid()->toString()]);
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
        $partnerRequest->load(['author:id,name', 'recipient:id,name']);

        return view('requests.show', ['methods' => SettlementMethod::query()->whereIn('kind', ['action', 'kisses', 'sushi', 'custom'])->where(fn (Builder $query) => $query->whereNull('user_id')->orWhere('user_id', auth()->id()))->orderBy('sort_order')->get(), 'couple' => $couple, 'partnerRequest' => $partnerRequest, 'conditions' => $partnerRequest->conditions()->latest('version')->paginate(self::PAGE_SIZE), 'currentCondition' => $partnerRequest->conditions()->latest('version')->first()]);
    }
}
