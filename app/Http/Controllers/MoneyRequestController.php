<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Money\CreateMoneyRequest;
use App\Enums\Currency;
use App\Enums\RequestType;
use App\Http\Requests\StoreMoneyRequest;
use App\Models\Couple;
use App\Models\ExpenseCategory;
use App\Models\RequestTemplate;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class MoneyRequestController extends Controller
{
    public function __construct(private readonly CreateMoneyRequest $create) {}

    public function create(Request $request, Couple $couple): View
    {
        Gate::authorize('update', $couple);

        $preset = $request->filled('request_template_id') ? RequestTemplate::query()->where('type', RequestType::Money)->findOrFail($request->integer('request_template_id')) : null;

        return view('money.create', ['preset' => $preset, 'couple' => $couple, 'currencies' => Currency::cases(), 'expenses' => ExpenseCategory::query()->orderBy('sort_order')->get(), 'operationKey' => Str::uuid()->toString()]);
    }

    public function store(StoreMoneyRequest $request, Couple $couple): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $created = $this->create->execute($actor, $couple, $request->getData($couple));

        return redirect()->route('requests.show', [$couple, $created])->with('status', __('Запрос денег отправлен. Реквизиты откроются после согласия партнёра.'));
    }
}
