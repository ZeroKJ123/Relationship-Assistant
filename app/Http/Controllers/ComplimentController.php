<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Profiles\SaveCompliments;
use App\Http\Requests\StoreComplimentsRequest;
use App\Models\Couple;
use App\Models\CoupleMember;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ComplimentController extends Controller
{
    public function __construct(private readonly SaveCompliments $save) {}

    public function index(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);

        return view('profile.compliments', ['user' => $actor, 'couple' => Couple::query()->whereIn('id', CoupleMember::query()->select('couple_id')->where('active_user_id', $actor->id))->first()]);
    }

    public function store(StoreComplimentsRequest $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $texts = $request->validated('compliments', []);
        $this->save->execute($actor, array_values(array_filter($texts, is_string(...))));

        return redirect()->route('profile.compliments.index')->with('status', __('Комплименты сохранены. Партнёр увидит их на главной.'));
    }
}
