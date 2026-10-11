<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Couples\UpdateTimezone;
use App\Http\Requests\UpdateTimezoneRequest;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class ProfileTimezoneController extends Controller
{
    public function __construct(private readonly UpdateTimezone $updateTimezone) {}

    public function __invoke(UpdateTimezoneRequest $request, Couple $couple): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $this->updateTimezone->execute($actor, $couple, $request->string('timezone')->toString(), $request->string('expected_timezone')->toString());

        return redirect()->route('profile.show')->with('status', __('Часовой пояс сохранён.'));
    }
}
