<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Dish;
use App\Models\PartnerRequest;
use App\Models\RequestCondition;
use App\Models\WishlistItem;
use App\Services\Media\PrivatePhotos;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PrivatePhotoController extends Controller
{
    public function dish(Dish $dish): StreamedResponse
    {
        Gate::authorize('view', $dish);

        return $this->respond($dish->photo_path);
    }

    public function wishlist(WishlistItem $wishlistItem): StreamedResponse
    {
        Gate::authorize('view', $wishlistItem);

        return $this->respond($wishlistItem->photo_path);
    }

    public function request(PartnerRequest $partnerRequest): StreamedResponse
    {
        Gate::authorize('view', $partnerRequest);
        $path = $partnerRequest->dish_snapshot['photo_path'] ?? null;

        return $this->respond(is_string($path) ? $path : null);
    }

    public function condition(RequestCondition $requestCondition): StreamedResponse
    {
        Gate::authorize('view', $requestCondition->partnerRequest);

        return $this->respond($requestCondition->gift_snapshot['photo_path'] ?? null);
    }

    private function respond(?string $path): StreamedResponse
    {
        abort_if($path === null || ! Storage::disk(PrivatePhotos::DISK)->exists($path), Response::HTTP_NOT_FOUND);

        return Storage::disk(PrivatePhotos::DISK)->response($path, null, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'], 'inline');
    }
}
