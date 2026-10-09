<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Requests\CreatePartnerRequest;
use App\Enums\RequestType;
use App\Http\Requests\StorePartnerRequest;
use App\Models\Couple;
use App\Models\PartnerRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
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

    public function create(Couple $couple): View
    {
        Gate::authorize('update', $couple);

        return view('requests.create', ['couple' => $couple, 'types' => RequestType::cases(), 'operationKey' => Str::uuid()->toString()]);
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

        return view('requests.show', ['couple' => $couple, 'partnerRequest' => $partnerRequest, 'conditions' => $partnerRequest->conditions()->latest('version')->paginate(self::PAGE_SIZE), 'currentCondition' => $partnerRequest->conditions()->latest('version')->first()]);
    }
}
