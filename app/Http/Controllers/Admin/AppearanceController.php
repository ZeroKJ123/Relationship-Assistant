<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\UpdateAppearance;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminAppearanceRequest;
use App\Models\User;
use App\Services\Appearance\AppearanceCatalog;
use App\Services\Appearance\AppearanceStore;
use App\Services\Couples\DashboardData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

final class AppearanceController extends Controller
{
    public function __construct(private readonly AppearanceStore $appearance, private readonly AppearanceCatalog $catalog, private readonly UpdateAppearance $update, private readonly DashboardData $dashboard) {}

    public function index(Request $request): View
    {
        $section = match (true) {
            $request->routeIs('admin.panel.icons') => 'icons', $request->routeIs('admin.panel.builder') => 'builder', default => 'themes'
        };
        $settings = $this->appearance->get();
        $editId = $request->string('edit')->toString();
        $isNew = $request->boolean('new');
        $editId = isset($settings['themes'][$editId]) ? $editId : $settings['active'];
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_FORBIDDEN);

        return view(sprintf('admin.appearance.%s', $section), ['appearance' => $this->appearance, 'settings' => $settings, 'revision' => $this->appearance->revision(), 'activeId' => $this->appearance->activeId(), 'editId' => $isNew ? Str::uuid()->toString() : $editId, 'editTheme' => $isNew ? $this->catalog->defaults()['themes']['classic'] : $settings['themes'][$editId], 'isNew' => $isNew, 'catalog' => $this->catalog, 'dashboard' => $section === 'builder' ? $this->dashboard->get($actor) : null]);
    }

    public function update(AdminAppearanceRequest $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_FORBIDDEN);
        $this->update->execute($actor, $request->getData(), $request);
        $route = match ($request->string('section')->toString()) {
            'icons' => 'admin.panel.icons', 'layout' => 'admin.panel.builder', default => 'admin.panel.themes'
        };

        return redirect()->route($route)->with('toast', __('Настройки сохранены для всех пользователей.'));
    }
}
