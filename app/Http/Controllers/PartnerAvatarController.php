<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Couple;
use App\Models\User;
use App\Services\Media\PrivatePhotos;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PartnerAvatarController extends Controller
{
    public function __invoke(Request $request, User $user): StreamedResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $allowed = $actor->id === $user->id || Couple::query()->whereRelation('members', 'active_user_id', $actor->id)->whereRelation('members', 'active_user_id', $user->id)->exists();
        abort_unless($allowed && $user->avatar_path !== null && Storage::disk(PrivatePhotos::DISK)->exists($user->avatar_path), Response::HTTP_NOT_FOUND);

        return Storage::disk(PrivatePhotos::DISK)->response($user->avatar_path, 'avatar.jpg', ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
