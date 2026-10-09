<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\UpdateEconomySettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEconomySettingsRequest;
use App\Models\EconomySettingsVersion;
use App\Models\User;
use App\Services\Hearts\EconomySettingsStore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class EconomySettingController extends Controller
{
    private const PAGE_SIZE = 10;

    public function __construct(private readonly EconomySettingsStore $store, private readonly UpdateEconomySettings $updateSettings) {}

    public function index(): View
    {
        Gate::authorize('manage-system-settings');
        $current = $this->store->initialize();

        return view('admin.economy.index', ['current' => $current, 'labels' => $this->store->labels(), 'history' => EconomySettingsVersion::query()->latest('version')->paginate(self::PAGE_SIZE)]);
    }

    public function update(UpdateEconomySettingsRequest $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $version = $this->updateSettings->execute($actor, $request->getData());

        return redirect()->route('admin.economy.index')->with('status', __('Сохранена версия :version.', ['version' => $version->version]));
    }
}
