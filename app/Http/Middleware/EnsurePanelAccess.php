<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Admin\PanelAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class EnsurePanelAccess
{
    public function __construct(private readonly PanelAccess $access) {}

    /** @param Closure(Request): HttpResponse $next */
    public function handle(Request $request, Closure $next): HttpResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->access->isOwner($user), Response::HTTP_NOT_FOUND);
        if (! $this->access->isUnlocked($request, $user)) {
            $this->access->clear($request);

            return redirect()->route('admin.panel.login');
        }

        return $next($request);
    }
}
