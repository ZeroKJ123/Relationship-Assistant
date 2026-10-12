<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CreateSystemRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminSystemRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class SystemRequestController extends Controller
{
    public function __construct(private readonly CreateSystemRequest $requests) {}

    public function __invoke(AdminSystemRequest $request): RedirectResponse
    {
        $admin = $request->user();
        abort_unless($admin instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->requests->execute($admin, User::query()->findOrFail($request->integer('recipient_id')), $request->string('description')->trim()->toString(), $request->string('operation_key')->toString());

        return redirect()->route('admin.panel.index')->with('status', __('Системная тестовая просьба отправлена.'));
    }
}
