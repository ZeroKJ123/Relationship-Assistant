<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\AdjustBalance;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminBalanceRequest;
use App\Models\HeartWallet;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

final class BalanceController extends Controller
{
    private const PAGE_SIZE = 20;

    public function __construct(private readonly AdjustBalance $adjust) {}

    public function index(Request $request): View
    {
        $search = trim(Str::substr($request->string('search')->toString(), 0, 80));
        $users = User::query()->select(['id', 'name', 'login']);
        if ($search !== '') {
            $users->where(fn ($query) => $query->where('login', 'like', sprintf('%%%s%%', $search))->orWhere('name', 'like', sprintf('%%%s%%', $search)));
        }
        $page = $users->orderBy('id')->paginate(self::PAGE_SIZE)->withQueryString();

        return view('admin.balance.index', ['users' => $page, 'search' => $search, 'wallets' => HeartWallet::query()->whereIn('user_id', $page->pluck('id'))->get()->keyBy('user_id'), 'operationKey' => Str::uuid()->toString()]);
    }

    public function update(AdminBalanceRequest $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_FORBIDDEN);
        $owner = User::query()->findOrFail($request->integer('user_id'));
        $entry = $this->adjust->execute($actor, $owner, $request->getData(), $request);

        return redirect()->route('admin.panel.balance.index')->with('toast', __('Баланс изменён. Теперь :balance сердечек.', ['balance' => $entry->balance_after]));
    }
}
