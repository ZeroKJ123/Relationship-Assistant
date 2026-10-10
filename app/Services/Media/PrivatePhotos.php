<?php

declare(strict_types=1);

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class PrivatePhotos
{
    public const DISK = 'catalog_photos';

    public const MAXIMUM_KILOBYTES = 4096;

    private const MAXIMUM_DIMENSION = 4096;

    private const OUTPUT_DIMENSION = 1200;

    private const JPEG_QUALITY = 85;

    public function store(?UploadedFile $photo): ?string
    {
        if ($photo === null) {
            return null;
        }
        try {
            Validator::make(['photo' => $photo], ['photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', sprintf('max:%d', self::MAXIMUM_KILOBYTES), sprintf('dimensions:max_width=%d,max_height=%d', self::MAXIMUM_DIMENSION, self::MAXIMUM_DIMENSION)]])->validate();
        } catch (ValidationException $exception) {
            Log::warning('catalog.photo_denied', ['reason' => 'invalid_image']);
            throw $exception;
        }
        $source = imagecreatefromstring($photo->getContent());
        if ($source === false) {
            throw ValidationException::withMessages(['photo' => __('Изображение не удалось прочитать.')]);
        }
        $width = imagesx($source);
        $height = imagesy($source);
        $targetWidth = min(self::OUTPUT_DIMENSION, $width);
        $targetHeight = max(1, intdiv($height * $targetWidth, $width));
        if ($targetHeight > self::OUTPUT_DIMENSION) {
            $targetHeight = self::OUTPUT_DIMENSION;
            $targetWidth = max(1, intdiv($width * $targetHeight, $height));
        }
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($target === false) {
            throw new RuntimeException('Photo buffer allocation failed.');
        }
        $background = imagecolorallocate($target, 255, 255, 255);
        if ($background === false) {
            throw new RuntimeException('Photo background allocation failed.');
        }
        imagefill($target, 0, 0, $background);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        ob_start();
        $isEncoded = imagejpeg($target, null, self::JPEG_QUALITY);
        $bytes = ob_get_clean();
        if (! $isEncoded || $bytes === false) {
            throw new RuntimeException('Photo encoding failed.');
        }
        $path = sprintf('%s.jpg', Str::uuid()->toString());
        try {
            if (! Storage::disk(self::DISK)->put($path, $bytes)) {
                throw new RuntimeException('Private photo storage failed.');
            }
        } catch (Throwable $exception) {
            Log::error('catalog.photo_storage_failed', ['disk' => self::DISK, 'operation' => 'write']);
            throw $exception;
        }
        Log::debug('catalog.photo_stored', ['width' => $targetWidth, 'height' => $targetHeight]);

        return $path;
    }

    public function discard(?string $path): void
    {
        if ($path !== null && ! Storage::disk(self::DISK)->delete($path)) {
            throw new RuntimeException('Unused photo cleanup failed.');
        }
    }
}
