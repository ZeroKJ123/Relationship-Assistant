<?php

declare(strict_types=1);

use App\Actions\Profiles\UpdateProfile;
use App\Enums\Gender;
use App\Models\TelegramAccount;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/** @return array{name: string, login: string, password: string, password_confirmation: string, gender: string} */
function registrationPayload(): array
{
    return ['name' => 'Анна', 'login' => 'anna_test', 'password' => 'safePassword123', 'password_confirmation' => 'safePassword123', 'gender' => 'female'];
}

it('registers by login without requiring email and never accepts admin input', function (): void {
    $this->post(route('register.store'), [...registrationPayload(), 'is_admin' => true])->assertRedirect(route('profile.show'));
    $user = User::query()->where('login', 'anna_test')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->email)->toBeNull()->and($user->is_admin)->toBeFalse()->and(Hash::check('safePassword123', $user->password))->toBeTrue();
    expect($user->recovery_codes)->toHaveCount(8);
});

it('shows recovery codes only on the initial profile visit', function (): void {
    $this->post(route('register.store'), registrationPayload());
    $this->withoutVite()->get(route('profile.show'))->assertSee('Сохраните резервные коды')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    $this->withoutVite()->get(route('profile.show'))->assertDontSee('Сохраните резервные коды');
});

it('normalizes logins and rejects duplicates', function (): void {
    User::factory()->create(['login' => 'anna_test']);
    $this->post(route('register.store'), [...registrationPayload(), 'login' => 'ANNA_TEST'])->assertSessionHasErrors('login');
    $this->assertGuest();
});

it('requires a confirmed sufficiently strong password', function (): void {
    $this->post(route('register.store'), [...registrationPayload(), 'password' => 'short', 'password_confirmation' => 'different'])->assertSessionHasErrors('password');
    expect(User::query()->count())->toBe(0);
});

it('rotates the session after a successful login', function (): void {
    $user = User::factory()->create(['login' => 'anna_test', 'password' => 'safePassword123']);
    $this->withSession(['marker' => true]);
    $sessionId = session()->getId();
    $this->post(route('login.store'), ['login' => 'ANNA_TEST', 'password' => 'safePassword123'])->assertRedirect(route('profile.show'));
    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($sessionId);
});

it('limits repeated unsuccessful login attempts', function (): void {
    User::factory()->create(['login' => 'anna_test']);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('login.store'), ['login' => 'anna_test', 'password' => 'invalid'])->assertSessionHasErrors('login');
    }
    $this->post(route('login.store'), ['login' => 'anna_test', 'password' => 'password'])
        ->assertSessionHasErrors(['login' => 'Слишком много попыток. Попробуйте через минуту.']);
    $this->assertGuest();
});

it('logs out and invalidates the authenticated session', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));
    $this->assertGuest();
    $this->get(route('profile.show'))->assertRedirect(route('login'));
});

it('encrypts payment details and excludes private account fields from serialization', function (): void {
    $user = User::factory()->create(['payment_details' => 'private account details', 'recovery_codes' => [Hash::make('secret code')]]);
    $stored = DB::table('users')->where('id', $user->id)->value('payment_details');
    expect($stored)->not->toBe('private account details')->and($user->fresh()->payment_details)->toBe('private account details');
    expect($user->toArray())->not->toHaveKeys(['payment_details', 'recovery_codes', 'password', 'is_admin', 'email']);
});

it('rejects changes to another user profile even when the action is called directly', function (): void {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    expect(fn (): null => app(UpdateProfile::class)->execute($actor, $target, 'Changed', Gender::Male, null))->toThrow(AuthorizationException::class);
    expect($target->fresh()->name)->toBe($target->name);
});

it('updates only the authenticated profile and ignores role and owner IDs', function (): void {
    $actor = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($actor)->patch(route('profile.update'), ['name' => 'Новое имя', 'gender' => 'unspecified', 'user_id' => $other->id, 'is_admin' => true])->assertRedirect(route('profile.show'));
    expect($actor->fresh()->name)->toBe('Новое имя')->and($actor->fresh()->is_admin)->toBeFalse()->and($other->fresh()->name)->toBe($other->name);
});

it('supports Telegram-only accounts without an invented email or password', function (): void {
    $user = User::factory()->create(['email' => null, 'password' => null, 'login' => null]);
    $identity = TelegramAccount::factory()->create(['user_id' => $user->id]);
    expect($user->telegramAccount->id)->toBe($identity->id)->and($identity->toArray())->not->toHaveKeys(['telegram_id', 'chat_id']);
});

it('prevents duplicate Telegram identities', function (): void {
    $identity = TelegramAccount::factory()->create();
    expect(fn (): TelegramAccount => TelegramAccount::factory()->create(['telegram_id' => $identity->telegram_id]))->toThrow(QueryException::class);
});

it('consumes a recovery code once and replaces the password', function (): void {
    $code = 'AbCdEfGhIjKlMnOpQrStUvWx';
    $user = User::factory()->create(['login' => 'anna_test', 'recovery_codes' => [Hash::make($code)]]);
    $payload = ['login' => 'anna_test', 'recovery_code' => $code, 'password' => 'newPassword123', 'password_confirmation' => 'newPassword123'];
    $this->post(route('recover.store'), $payload)->assertRedirect(route('login'));
    expect($user->fresh()->recovery_codes)->toBe([])->and(Hash::check('newPassword123', $user->fresh()->password))->toBeTrue();
    $this->post(route('recover.store'), $payload)->assertSessionHasErrors('recovery_code');
});

it('does not reveal whether the login or the recovery code is incorrect', function (): void {
    $this->post(route('recover.store'), ['login' => 'missing', 'recovery_code' => str_repeat('x', 24), 'password' => 'newPassword123', 'password_confirmation' => 'newPassword123'])->assertSessionHasErrors('recovery_code')->assertSessionMissing('_old_input.recovery_code');
});

it('encrypts the temporary recovery code list stored in the session', function (): void {
    $this->post(route('register.store'), registrationPayload());
    $encryptedCodes = session('recovery_codes');
    expect($encryptedCodes)->toBeString();
    $codes = explode("\n", Crypt::decryptString($encryptedCodes));
    expect($codes)->toHaveCount(8)->and($encryptedCodes)->not->toContain($codes[0]);
});

it('revokes persistent sessions for the recovered user only', function (): void {
    $code = 'AbCdEfGhIjKlMnOpQrStUvWx';
    $user = User::factory()->create(['login' => 'anna_test', 'recovery_codes' => [Hash::make($code)]]);
    $other = User::factory()->create();
    DB::table('sessions')->insert([
        ['id' => 'recovered-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
        ['id' => 'other-session', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
    ]);
    $this->post(route('recover.store'), ['login' => 'anna_test', 'recovery_code' => $code, 'password' => 'newPassword123', 'password_confirmation' => 'newPassword123'])->assertRedirect(route('login'));
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $other->id)->count())->toBe(1);
});

it('requires a CSRF token for browser registration requests', function (): void {
    $this->app->instance('env', 'local');
    $this->post(route('register.store'), registrationPayload())->assertSee('Page Expired');
    expect(User::query()->count())->toBe(0);
});

it('rejects an existing authenticated session after a password change', function (): void {
    $user = User::factory()->create(['password' => 'originalPassword123']);
    $this->actingAs($user)->withSession(['password_hash_web' => $user->password]);
    $user->forceFill(['password' => 'differentPassword123'])->save();
    $this->get(route('profile.show'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('validates malformed recovery identities without crashing before validation', function (): void {
    $this->post(route('recover.store'), ['login' => ['invalid'], 'recovery_code' => str_repeat('x', 24), 'password' => 'newPassword123', 'password_confirmation' => 'newPassword123'])->assertSessionHasErrors('login');
});
