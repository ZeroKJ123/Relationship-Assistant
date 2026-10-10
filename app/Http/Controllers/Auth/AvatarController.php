<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Media\PrivatePhotos;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AvatarController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->avatar_path !== null, Response::HTTP_NOT_FOUND);

        return Storage::disk(PrivatePhotos::DISK)->response($user->avatar_path, 'avatar.jpg', ['Content-Type' => 'image/jpeg']);
    }
}
