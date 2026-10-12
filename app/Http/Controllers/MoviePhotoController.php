<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Couple;
use App\Models\Movie;
use App\Models\User;
use App\Services\Media\PrivatePhotos;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MoviePhotoController extends Controller
{
    public function __invoke(Request $request, Movie $movie): StreamedResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);
        $allowed = $actor->id === $movie->user_id || Couple::query()->whereRelation('members', 'active_user_id', $actor->id)->whereRelation('members', 'active_user_id', $movie->user_id)->exists();
        abort_unless($allowed && $movie->photo_path !== null && Storage::disk(PrivatePhotos::DISK)->exists($movie->photo_path), Response::HTTP_NOT_FOUND);

        return Storage::disk(PrivatePhotos::DISK)->response($movie->photo_path, 'movie.jpg', ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
