<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Hearts\AdjustPartnerHearts;
use App\Http\Requests\AdjustHeartsRequest;
use App\Models\Couple;
use App\Models\CoupleMember;
use App\Models\User;
use App\Services\Hearts\EconomyRules;
use App\Services\Hearts\Ledger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

final class HeartController extends Controller
{
    private const PAGE_SIZE = 20;

    private const DEFAULT_TIMEZONE = 'Europe/Minsk';

    public function __construct(private readonly Ledger $ledger, private readonly AdjustPartnerHearts $adjust, private readonly EconomyRules $rules) {}

    public function index(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $wallet = $this->ledger->getWallet($actor);
        $couple = Couple::query()->whereIn('id', CoupleMember::query()->select('couple_id')->where('active_user_id', $actor->id))->first();

        return view('hearts.index', ['wallet' => $wallet, 'history' => $wallet->transactions()->latest('id')->paginate(self::PAGE_SIZE), 'couple' => $couple, 'timezone' => $couple === null ? self::DEFAULT_TIMEZONE : $couple->timezone, 'operationKey' => Str::uuid()->toString(), 'maximum' => max($this->rules->get('manual_thanks_daily'), $this->rules->get('manual_deduction_daily')), 'thanksDaily' => $this->rules->get('manual_thanks_daily'), 'thanksWeekly' => $this->rules->get('manual_thanks_weekly'), 'deductionDaily' => $this->rules->get('manual_deduction_daily'), 'deductionWeekly' => $this->rules->get('manual_deduction_weekly')]);
    }

    public function store(AdjustHeartsRequest $request, Couple $couple): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $entry = $this->adjust->execute($actor, $couple, $request->getData());

        return redirect()->route('hearts.index')->with('status', __('Изменение сердечек партнёра: :amount.', ['amount' => sprintf('%+d', $entry->amount)]));
    }
}
