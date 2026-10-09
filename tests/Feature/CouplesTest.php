<?php

declare(strict_types=1);

use App\Actions\Couples\AcceptInvitation;
use App\Actions\Couples\EndCouple;
use App\Actions\Couples\InvitePartner;
use App\Data\Couples\CoupleSetupData;
use App\Enums\CoupleStatus;
use App\Enums\InvitationStatus;
use App\Models\Couple;
use App\Models\CoupleInvitation;
use App\Models\CoupleMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // View behavior is independent of generated Vite files; CI builds assets separately.
    $this->withoutVite();
});

it('requires authentication for private couple pages', function (): void {
    $this->get(route('couples.index'))->assertRedirect(route('login'));
    $this->get(route('couples.create'))->assertRedirect(route('login'));
});

it('issues a hashed invitation with a one-time encrypted link and seven-day lifetime', function (): void {
    $this->freezeTime();
    $author = User::factory()->create();
    $response = $this->asUser($author)->post(route('invitations.store'), ['started_on' => '2025-01-01', 'timezone' => 'Europe/Minsk', 'user_id' => 999, 'status' => 'active']);
    $couple = Couple::query()->sole();
    $invitation = CoupleInvitation::query()->sole();
    $response->assertRedirect(route('couples.show', $couple));
    $link = Crypt::decryptString(session('invitation_link'));
    $token = basename($link);
    expect($invitation->token_hash)->toBe(hash('sha256', $token));
    expect($invitation->expires_at->toDateTimeString() === now()->addDays(7)->toDateTimeString())->toBeTrue();
    expect($couple->status)->toBe(CoupleStatus::Pending);
    expect($couple->members()->sole()->user_id)->toBe($author->id);
    expect($invitation->toArray())->not->toHaveKey('token_hash');
    $this->get(route('couples.show', $couple))->assertOk()->assertSee($link);
    $this->get(route('couples.show', $couple))->assertOk()->assertDontSee($link);
});

it('validates the date and IANA timezone', function (array $input, string $error): void {
    $this->asUser(User::factory()->create())->post(route('invitations.store'), $input)->assertSessionHasErrors($error);
    expect(Couple::query()->count())->toBe(0);
})->with([
    [['started_on' => '2999-01-01', 'timezone' => 'Europe/Minsk'], 'started_on'],
    [['started_on' => '2025-02-30', 'timezone' => 'Europe/Minsk'], 'started_on'],
    [['started_on' => '2025-01-01', 'timezone' => 'invalid'], 'timezone'],
    [['started_on' => '2025-01-01', 'timezone' => ['Europe/Minsk']], 'timezone'],
]);

it('accepts only after confirmation and gives both members equal access', function (): void {
    $author = User::factory()->create();
    $guest = User::factory()->create();
    $issued = app(InvitePartner::class)->execute($author, new CoupleSetupData(CarbonImmutable::yesterday(), 'Europe/Minsk'));
    $this->asUser($guest)->get(route('invitations.show', $issued->token))->assertOk()->assertSee($author->name);
    $this->post(route('invitations.accept', $issued->token))->assertSessionHasErrors('confirmation');
    $this->post(route('invitations.accept', $issued->token), ['confirmation' => '1', 'user_id' => $author->id])->assertRedirect(route('couples.show', $issued->coupleId));
    $couple = Couple::query()->findOrFail($issued->coupleId);
    expect($couple->status)->toBe(CoupleStatus::Active);
    expect($couple->members()->count())->toBe(2);
    foreach ([$author, $guest] as $member) {
        expect(Gate::forUser($member)->allows('update', $couple))->toBeTrue();
        $this->asUser($member)->get(route('couples.show', $couple))->assertOk();
    }
    $this->asUser($guest)->post(route('invitations.accept', $issued->token), ['confirmation' => '1'])->assertRedirect();
    expect($couple->members()->count())->toBe(2);
});

it('denies third members, strangers and administrators access to private history', function (): void {
    $author = User::factory()->create();
    $issued = app(InvitePartner::class)->execute($author, new CoupleSetupData(CarbonImmutable::yesterday(), 'Europe/Minsk'));
    app(AcceptInvitation::class)->execute(User::factory()->create(), $issued->token);
    $stranger = User::factory()->create();
    $stranger->forceFill(['is_admin' => true])->save();
    $this->asUser($stranger)->get(route('couples.show', $issued->coupleId))->assertForbidden();
    $this->get(route('couples.end', $issued->coupleId))->assertForbidden();
    $this->post(route('couples.end_store', $issued->coupleId), ['confirmation' => '1'])->assertForbidden();
    $this->post(route('invitations.accept', $issued->token), ['confirmation' => '1'])->assertSessionHasErrors('invitation');
    expect(CoupleMember::query()->count())->toBe(2);
    expect(Gate::forUser($stranger)->allows('manage-system-settings'))->toBeTrue();
    expect(Gate::forUser($author)->allows('manage-system-settings'))->toBeFalse();
});

it('rejects expired, cancelled and own invitations', function (string $reason): void {
    $author = User::factory()->create();
    $issued = app(InvitePartner::class)->execute($author, new CoupleSetupData(CarbonImmutable::yesterday(), 'Europe/Minsk'));
    $invitation = CoupleInvitation::query()->findOrFail($issued->invitationId);
    if ($reason === 'boundary') {
        $this->freezeTime();
        $invitation->forceFill(['expires_at' => now()])->save();
    }
    if ($reason === 'expired') {
        $invitation->forceFill(['expires_at' => now()->subSecond()])->save();
    }
    if ($reason === 'cancelled') {
        $this->asUser($author)->delete(route('invitations.destroy', $invitation))->assertRedirect();
    }
    $actor = $reason === 'own' ? $author : User::factory()->create();
    $this->asUser($actor)->post(route('invitations.accept', $issued->token), ['confirmation' => '1'])->assertSessionHasErrors('invitation');
    expect(CoupleMember::query()->count())->toBe(1);
})->with(['expired', 'boundary', 'cancelled', 'own']);

it('reissues only for the existing pending pair and revokes the old token', function (): void {
    $author = User::factory()->create();
    $data = new CoupleSetupData(CarbonImmutable::yesterday(), 'Europe/Minsk');
    $first = app(InvitePartner::class)->execute($author, $data);
    $second = app(InvitePartner::class)->execute($author, $data);
    expect($second->coupleId)->toBe($first->coupleId);
    expect(Couple::query()->count())->toBe(1);
    expect(CoupleInvitation::query()->findOrFail($first->invitationId)->status)->toBe(InvitationStatus::Cancelled);
    $this->asUser(User::factory()->create())->delete(route('invitations.destroy', $second->invitationId))->assertForbidden();
    $this->post(route('invitations.accept', $first->token), ['confirmation' => '1'])->assertSessionHasErrors('invitation');
});

it('prevents joining while another pending or active pair exists', function (): void {
    $author = User::factory()->create();
    $guest = User::factory()->create();
    $data = new CoupleSetupData(CarbonImmutable::yesterday(), 'Europe/Minsk');
    $issued = app(InvitePartner::class)->execute($author, $data);
    $own = app(InvitePartner::class)->execute($guest, $data);
    $this->asUser($guest)->post(route('invitations.accept', $issued->token), ['confirmation' => '1'])->assertSessionHasErrors('invitation');
    app(EndCouple::class)->execute($guest, Couple::query()->findOrFail($own->coupleId));
    app(AcceptInvitation::class)->execute($guest, $issued->token);
    expect(fn () => app(InvitePartner::class)->execute($guest, $data))->toThrow(ValidationException::class);
});

it('ends a pair only after confirmation and preserves history from a new partner', function (): void {
    $author = User::factory()->create();
    $guest = User::factory()->create();
    $newPartner = User::factory()->create();
    $data = new CoupleSetupData(CarbonImmutable::parse('2025-01-01'), 'Europe/Minsk');
    $issued = app(InvitePartner::class)->execute($author, $data);
    $couple = app(AcceptInvitation::class)->execute($guest, $issued->token);
    $this->asUser($guest)->post(route('couples.end_store', $couple))->assertSessionHasErrors('confirmation');
    $this->post(route('couples.end_store', $couple), ['confirmation' => '1'])->assertRedirect(route('couples.index'));
    expect($couple->refresh()->status)->toBe(CoupleStatus::Ended);
    expect(CoupleMember::query()->whereNotNull('active_user_id')->count())->toBe(0);
    app(EndCouple::class)->execute($author, $couple);
    $second = app(InvitePartner::class)->execute($author, $data);
    app(AcceptInvitation::class)->execute($newPartner, $second->token);
    $this->asUser($author)->get(route('couples.show', $couple))->assertOk()->assertSee('История сохранена');
    $this->asUser($guest)->get(route('couples.show', $couple))->assertOk();
    $this->asUser($newPartner)->get(route('couples.show', $couple))->assertForbidden();
    $this->get(route('couples.index'))->assertDontSee(route('couples.show', $couple));
});

it('cancels all outstanding invitations when a pending pair ends', function (): void {
    $author = User::factory()->create();
    $issued = app(InvitePartner::class)->execute($author, new CoupleSetupData(CarbonImmutable::yesterday(), 'Europe/Minsk'));
    app(EndCouple::class)->execute($author, Couple::query()->findOrFail($issued->coupleId));
    $this->asUser(User::factory()->create())->post(route('invitations.accept', $issued->token), ['confirmation' => '1'])->assertSessionHasErrors('invitation');
    expect(CoupleInvitation::query()->sole()->status)->toBe(InvitationStatus::Cancelled);
});

it('does not leak an invitation link into a different historical pair', function (): void {
    $author = User::factory()->create();
    $data = new CoupleSetupData(CarbonImmutable::yesterday(), 'Europe/Minsk');
    $old = app(InvitePartner::class)->execute($author, $data);
    app(EndCouple::class)->execute($author, Couple::query()->findOrFail($old->coupleId));
    $new = app(InvitePartner::class)->execute($author, $data);
    $link = route('invitations.show', $new->token);
    $this->asUser($author)->withSession(['invitation_link' => Crypt::encryptString($link), 'invitation_link_couple' => $new->coupleId])->get(route('couples.show', $old->coupleId))->assertDontSee($link);
});

it('counts calendar days in the couple timezone', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 22:30:00', 'UTC'));
    $author = User::factory()->create();
    $issued = app(InvitePartner::class)->execute($author, new CoupleSetupData(CarbonImmutable::parse('2026-10-09'), 'Europe/Minsk'));
    $this->asUser($author)->get(route('couples.show', $issued->coupleId))->assertSee('дней вместе: 1');
});

it('keeps invitation continuation available after registration', function (): void {
    $author = User::factory()->create();
    $issued = app(InvitePartner::class)->execute($author, new CoupleSetupData(CarbonImmutable::yesterday(), 'Europe/Minsk'));
    $url = route('invitations.show', $issued->token);
    $this->get($url)->assertRedirect(route('login'));
    $this->post(route('register.store'), ['name' => 'Новый участник', 'login' => 'newpartner', 'password' => 'Test-password-123', 'password_confirmation' => 'Test-password-123', 'gender' => 'unspecified'])->assertRedirect(route('profile.show'));
    $this->get(route('profile.show'))->assertSee($url)->assertSee('Сохраните резервные коды');
});

it('requires CSRF protection for invitation acceptance outside the test bypass', function (): void {
    $issued = app(InvitePartner::class)->execute(User::factory()->create(), new CoupleSetupData(CarbonImmutable::yesterday(), 'Europe/Minsk'));
    $this->app->instance('env', 'local');
    $this->asUser(User::factory()->create())->post(route('invitations.accept', $issued->token), ['confirmation' => '1'])->assertSee('Page Expired');
    expect(CoupleMember::query()->count())->toBe(1);
});

it('escapes partner names and keeps private couple responses out of caches', function (): void {
    $author = User::factory()->create(['name' => '<script>alert(1)</script>']);
    $issued = app(InvitePartner::class)->execute($author, new CoupleSetupData(CarbonImmutable::yesterday(), 'Europe/Minsk'));
    $this->asUser($author)->get(route('couples.show', $issued->coupleId))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertHeader('Cache-Control', 'max-age=0, no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
});
