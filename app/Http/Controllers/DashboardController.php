<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Couples\DashboardData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class DashboardController extends Controller
{
    public function __construct(private readonly DashboardData $dashboard) {}

    public function __invoke(Request $request): View|Response
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return view('home');
        }
        try {
            return view('dashboard.index', ['dashboard' => $this->dashboard->get($actor)]);
        } catch (Throwable $exception) {
            $reference = Str::uuid()->toString();
            Log::error('dashboard.load_failed', ['user_id' => $actor->id, 'request_id' => $reference, 'exception' => $exception::class]);

            return response()->view('dashboard.error', ['reference' => $reference], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
