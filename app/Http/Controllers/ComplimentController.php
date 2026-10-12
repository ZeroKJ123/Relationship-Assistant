<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Profiles\SaveCompliments;
use App\Http\Requests\StoreComplimentsRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class ComplimentController extends Controller
{
    public function __construct(private readonly SaveCompliments $save) {}

    public function store(StoreComplimentsRequest $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $texts = $request->validated('compliments', []);
        $this->save->execute($actor, array_values(array_filter($texts, is_string(...))));

        return redirect()->route('profile.show')->with('status', __('Комплименты сохранены. Партнёр увидит их на главной.'));
    }
}
