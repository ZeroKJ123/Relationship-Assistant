<?php

declare(strict_types=1);

use App\Rules\SingleEmoji;
use Tests\TestCase;

uses(TestCase::class);

it('accepts one emoji including compound skin-tone and flag sequences', function (string $emoji): void {
    $failed = false;
    (new SingleEmoji)->validate('emoji', $emoji, function () use (&$failed): void {
        $failed = true;
    });
    expect($failed)->toBeFalse();
})->with(['🫠', '👩🏽‍💻', '🇧🇾', '❤️', '1️⃣', '👨‍👩‍👧‍👦']);

it('rejects plain text multiple emoji and malformed sequences', function (string $text): void {
    $failed = false;
    (new SingleEmoji)->validate('emoji', $text, function () use (&$failed): void {
        $failed = true;
    });
    expect($failed)->toBeTrue();
})->with(['hello', '😊😊', '1', '<script>', 'a😊', '', '🇧', '🏽']);
