<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AppearanceAssetController extends Controller
{
    public function __invoke(string $asset): StreamedResponse
    {
        $disk = Storage::disk('appearance_assets');
        abort_unless($disk->exists($asset), Response::HTTP_NOT_FOUND);

        return $disk->response($asset, headers: ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=86400']);
    }
}
