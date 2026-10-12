<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CoupleStatus;
use App\Enums\DeliveryStatus;
use App\Enums\DutyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminActAsRequest;
use App\Http\Requests\AdminPanelLoginRequest;
use App\Models\AdminAudit;
use App\Models\Couple;
use App\Models\Duty;
use App\Models\MoodEntry;
use App\Models\NotificationDelivery;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Services\Admin\PanelAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PanelController extends Controller
{
    private const PAGE_SIZE = 20;

    public function __construct(private readonly PanelAccess $access) {}

    public function create(Request $request): View
    {
        $this->requireOwner($request);

        return view('admin.panel.login');
    }

    public function store(AdminPanelLoginRequest $request): RedirectResponse
    {
        $owner = $this->requireOwner($request);
        $hash = config('admin_panel.password_hash');
        if (! is_string($hash) || ! Hash::check($request->string('password')->toString(), $hash)) {
            Log::warning('admin.panel_login_denied', ['admin_id' => $owner->id]);
            throw ValidationException::withMessages(['password' => __('Неверный пароль.')]);
        }
        $this->access->clear($request);
        $request->session()->regenerate();
        $request->session()->put('admin_panel', ['owner' => $owner->id, 'until' => now()->getTimestamp() + PanelAccess::SESSION_SECONDS, 'fingerprint' => hash('sha256', $hash)]);
        $this->audit($owner, 'panel.unlocked');

        return redirect()->route('admin.panel.index');
    }

    public function index(Request $request): View
    {
        $this->requireOwner($request);
        $search = trim(Str::substr($request->string('search')->toString(), 0, 80));
        $users = User::query()->select(['id', 'login', 'name', 'created_at'])->with(['coupleMemberships' => fn ($query) => $query->whereNotNull('active_user_id')->with('couple')]);
        if ($search !== '') {
            $users->where(fn ($query) => $query->where('login', 'like', sprintf('%%%s%%', $search))->orWhere('name', 'like', sprintf('%%%s%%', $search)));
        }

        return view('admin.panel.index', ['users' => $users->orderBy('id')->paginate(self::PAGE_SIZE)->withQueryString(), 'search' => $search, 'operationKey' => Str::uuid()->toString(), 'statistics' => [
            __('Пользователи') => User::query()->count(),
            __('Активные пары') => Couple::query()->where('status', CoupleStatus::Active)->count(),
            __('Просьбы') => PartnerRequest::query()->count(),
            __('Выполненные дела') => Duty::query()->where('status', DutyStatus::Confirmed)->count(),
            __('Ожидают проверки') => Duty::query()->where('status', DutyStatus::Reported)->count(),
            __('Оценки настроения') => MoodEntry::query()->count(),
            __('Уведомления в ожидании') => NotificationDelivery::query()->where('status', DeliveryStatus::Pending)->count(),
            __('Ошибки доставки') => NotificationDelivery::query()->where('status', DeliveryStatus::Failed)->count(),
        ], 'history' => AdminAudit::query()->latest('id')->paginate(self::PAGE_SIZE, ['*'], 'audit_page')]);
    }

    public function update(AdminActAsRequest $request): RedirectResponse
    {
        $owner = $this->requireOwner($request);
        $target = User::query()->findOrFail($request->integer('user_id'));
        abort_unless($target->id !== $owner->id, Response::HTTP_UNPROCESSABLE_ENTITY);
        $request->session()->put('admin_panel.target', $target->id);
        $this->audit($owner, 'panel.act_as', $target->id);

        return redirect()->route('home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $owner = $this->requireOwner($request);
        $this->audit($owner, 'panel.locked');
        $this->access->clear($request);
        $request->session()->regenerate();

        return redirect()->route('profile.show');
    }

    public function stop(Request $request): RedirectResponse
    {
        $owner = $this->requireOwner($request);
        $this->audit($owner, 'panel.stop_acting');
        $request->session()->forget('admin_panel.target');

        return redirect()->route('admin.panel.index');
    }

    private function requireOwner(Request $request): User
    {
        $owner = $request->user();
        abort_unless($owner instanceof User && $this->access->isOwner($owner), Response::HTTP_NOT_FOUND);

        return $owner;
    }

    private function audit(User $owner, string $operation, ?int $targetId = null): void
    {
        $audit = new AdminAudit;
        $audit->forceFill(['admin_id' => $owner->id, 'target_user_id' => $targetId, 'operation' => $operation])->save();
        Log::info('admin.panel_action', ['admin_id' => $owner->id, 'target_user_id' => $targetId, 'operation' => $operation]);
    }
}
