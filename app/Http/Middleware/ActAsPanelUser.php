<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AdminAudit;
use App\Models\User;
use App\Services\Admin\PanelAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class ActAsPanelUser
{
    public function __construct(private readonly PanelAccess $access) {}

    /** @param Closure(Request): HttpResponse $next */
    public function handle(Request $request, Closure $next): HttpResponse
    {
        $owner = $request->user();
        $targetId = $request->session()->get('admin_panel.target');
        if (! is_int($targetId) || ! $owner instanceof User || $request->routeIs('admin.*')) {
            return $next($request);
        }
        if (! $this->access->isUnlocked($request, $owner)) {
            $this->access->clear($request);

            return redirect()->route('admin.panel.login');
        }
        abort_if($request->routeIs('logout', 'profile.telegram.link', 'telegram.login.*'), Response::HTTP_FORBIDDEN);
        $target = User::query()->findOrFail($targetId);
        $request->attributes->set('admin_actor_id', $owner->id);
        Auth::guard('web')->setUser($target);
        $audit = null;
        if (! $request->isMethodSafe()) {
            $audit = new AdminAudit;
            $audit->forceFill(['admin_id' => $owner->id, 'target_user_id' => $target->id, 'operation' => sprintf('%s %s', $request->method(), $request->route()?->getName() ?? 'unknown')])->save();
        }
        try {
            $response = $next($request);
            if ($audit !== null) {
                $audit->forceFill(['response_status' => $response->getStatusCode()])->save();
            }

            return $response;
        } finally {
            Auth::guard('web')->setUser($owner);
        }
    }
}
