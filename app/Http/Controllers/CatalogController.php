<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Catalogs\CreateSettlementMethod;
use App\Http\Requests\StoreSettlementMethodRequest;
use App\Models\ExpenseCategory;
use App\Models\RequestCategory;
use App\Models\SettlementMethod;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

final class CatalogController extends Controller
{
    private const PAGE_SIZE = 20;

    public function __construct(private readonly CreateSettlementMethod $createMethod) {}

    public function index(Request $request): View
    {
        $owner = $request->user();
        abort_unless($owner instanceof User, Response::HTTP_UNAUTHORIZED);

        return view('catalogs.index', ['expenses' => ExpenseCategory::query()->orderBy('sort_order')->get(), 'categories' => RequestCategory::query()->orderBy('sort_order')->get(), 'methods' => SettlementMethod::query()->whereNull('user_id')->orWhere('user_id', $owner->id)->orderBy('sort_order')->orderBy('id')->paginate(self::PAGE_SIZE, ['*'], 'methods_page'), 'operationKey' => Str::uuid()->toString()]);
    }

    public function store(StoreSettlementMethodRequest $request): RedirectResponse
    {
        $owner = $request->user();
        abort_unless($owner instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->createMethod->execute($owner, $request->getData());

        return redirect()->route('catalogs.index')->with('status', __('Свой способ сохранён в вашем каталоге.'));
    }
}
