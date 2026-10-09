<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Couple;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class EnsureCoupleAccess
{
    /** @param Closure(Request): HttpResponse $next */
    public function handle(Request $request, Closure $next): HttpResponse
    {
        $couple = $request->route('couple');
        abort_unless($couple instanceof Couple, Response::HTTP_NOT_FOUND);
        Gate::authorize('view', $couple);

        return $next($request);
    }
}
