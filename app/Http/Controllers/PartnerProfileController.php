<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Couple;
use App\Models\Dish;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PartnerProfileController extends Controller
{
    public function show(Request $request, User $user): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $couple = Couple::query()->whereRelation('members', 'active_user_id', $actor->id)->whereRelation('members', 'active_user_id', $user->id)->first();
        abort_unless($couple !== null, Response::HTTP_NOT_FOUND);

        return view('profile.partner', ['profile' => $user, 'couple' => $couple, 'recipeCount' => Dish::query()->where('user_id', $user->id)->count()]);
    }
}
