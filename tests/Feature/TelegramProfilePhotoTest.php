<?php

declare(strict_types=1);

use App\Models\TelegramAccount;
use App\Models\User;
use App\Services\Media\PrivatePhotos;
use App\Services\Telegram\ProfilePhoto;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    config(['services.telegram.enabled' => true, 'services.telegram.bot_token' => '123456789:fake-test-token']);
});

it('imports an available photo privately outside transactions and serves only the owners avatar', function (): void {
    Storage::fake(PrivatePhotos::DISK);
    $account = TelegramAccount::factory()->create();
    $result = ['user' => $account->user, 'account' => $account];
    $image = UploadedFile::fake()->image('avatar.jpg', 80, 80)->getContent();
    // The action is tested without the outer RefreshDatabase transaction to verify the HTTP boundary.
    DB::commit();
    Http::fake(function (Request $request) use ($image): PromiseInterface {
        expect(DB::transactionLevel())->toBe(0);
        if (str_ends_with($request->url(), '/getUserProfilePhotos')) {
            return Http::response(['ok' => true, 'result' => ['photos' => [[['file_id' => 'photo-1']]]]]);
        }
        if (str_ends_with($request->url(), '/getFile')) {
            return Http::response(['ok' => true, 'result' => ['file_path' => 'photos/avatar.jpg', 'file_size' => strlen($image)]]);
        }

        return Http::response($image);
    });
    app(ProfilePhoto::class)->import($result['user'], $result['account']);
    expect($result['user']->refresh()->avatar_path)->not->toBeNull();
    $this->asUser($result['user'])->get(route('profile.avatar'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->asUser(User::factory()->create())->get(route('profile.avatar'))->assertNotFound();
});

it('falls back to the name initial when a photo is unavailable or unsafe', function (string $case): void {
    Storage::fake(PrivatePhotos::DISK);
    $account = TelegramAccount::factory()->create();
    Http::fakeSequence()->push(['ok' => true, 'result' => ['photos' => [[['file_id' => 'photo-1']]]]])->push(['ok' => true, 'result' => ['file_path' => $case === 'path' ? '../private.txt' : 'photos/avatar.jpg', 'file_size' => $case === 'large' ? 1048577 : 10]])->push('not an image');
    app(ProfilePhoto::class)->import($account->user, $account);
    expect($account->user->refresh()->avatar_path)->toBeNull();
    expect(Storage::disk(PrivatePhotos::DISK)->allFiles())->toBe([]);
})->with(['path', 'large', 'malformed']);
