<?php

declare(strict_types=1);

use App\Actions\Profiles\UpdateAvatar;
use App\Models\User;
use App\Services\Media\PrivatePhotos;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
    Storage::fake(PrivatePhotos::DISK);
});

it('uploads and replaces the owners avatar privately and removes the previous file', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->asUser($user)->post(route('profile.avatar.store'), ['photo' => UploadedFile::fake()->image('first.png', 100, 100), 'user_id' => $other->id])->assertRedirect(route('profile.show'))->assertSessionHasNoErrors();
    $firstPath = $user->refresh()->avatar_path;
    expect($firstPath)->not->toBeNull()->and($other->refresh()->avatar_path)->toBeNull();
    Storage::disk(PrivatePhotos::DISK)->assertExists($firstPath);
    $this->get(route('profile.avatar'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->post(route('profile.avatar.store'), ['photo' => UploadedFile::fake()->image('second.jpg', 100, 100)])->assertSessionHasNoErrors();
    expect($user->refresh()->avatar_path)->not->toBe($firstPath);
    Storage::disk(PrivatePhotos::DISK)->assertMissing($firstPath);
    Storage::disk(PrivatePhotos::DISK)->assertExists($user->avatar_path);
    $this->asUser($other)->get(route('profile.avatar'))->assertNotFound();
});

it('preserves the avatar when the submitted file is unsafe or too large', function (string $kind): void {
    $user = User::factory()->create(['avatar_path' => 'existing.jpg']);
    Storage::disk(PrivatePhotos::DISK)->put('existing.jpg', 'previous');
    $photo = match ($kind) {
        'large' => UploadedFile::fake()->image('large.jpg')->size(PrivatePhotos::MAXIMUM_KILOBYTES + 1),
        'dimensions' => UploadedFile::fake()->image('wide.jpg', 4097, 2),
        default => UploadedFile::fake()->createWithContent('photo.svg', '<svg><script>alert(1)</script></svg>'),
    };
    $this->asUser($user)->post(route('profile.avatar.store'), ['photo' => $photo])->assertSessionHasErrors('photo');
    expect($user->refresh()->avatar_path)->toBe('existing.jpg')->and(Storage::disk(PrivatePhotos::DISK)->allFiles())->toBe(['existing.jpg']);
})->with(['large', 'dimensions', 'svg']);

it('denies guest uploads and changes to another users avatar', function (): void {
    $actor = User::factory()->create();
    $other = User::factory()->create();
    $photo = UploadedFile::fake()->image('avatar.jpg');
    $this->post(route('profile.avatar.store'), ['photo' => $photo])->assertRedirect(route('login'));
    expect(fn () => app(UpdateAvatar::class)->execute($actor, $other, $photo))->toThrow(AuthorizationException::class);
    expect(Storage::disk(PrivatePhotos::DISK)->allFiles())->toBe([]);
});
