<?php

declare(strict_types=1);

use App\Actions\Couples\InvitePartner;
use App\Data\Couples\CoupleSetupData;
use App\Models\Couple;
use App\Models\CoupleMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\Support\ConcurrentInvitations;

// Committed fixtures are visible to independent PHP processes.
uses(DatabaseMigrations::class);

it('allows only one of two guests to accept the same invitation concurrently', function (): void {
    $issued = app(InvitePartner::class)->execute(User::factory()->create(), new CoupleSetupData(CarbonImmutable::yesterday(), 'Europe/Minsk'));
    $results = (new ConcurrentInvitations)->run([
        ['actor' => User::factory()->create(), 'token' => $issued->token],
        ['actor' => User::factory()->create(), 'token' => $issued->token],
    ]);
    sort($results);
    expect($results)->toBe(['accepted', 'rejected']);
    expect(CoupleMember::query()->where('couple_id', $issued->coupleId)->count())->toBe(2);
});

it('allows one actor to join only one of two couples concurrently', function (): void {
    $data = new CoupleSetupData(CarbonImmutable::yesterday(), 'Europe/Minsk');
    $first = app(InvitePartner::class)->execute(User::factory()->create(), $data);
    $second = app(InvitePartner::class)->execute(User::factory()->create(), $data);
    $guest = User::factory()->create();
    $results = (new ConcurrentInvitations)->run([
        ['actor' => $guest, 'token' => $first->token],
        ['actor' => $guest, 'token' => $second->token],
    ]);
    sort($results);
    expect($results)->toBe(['accepted', 'rejected']);
    expect(CoupleMember::query()->where('active_user_id', $guest->id)->count())->toBe(1);
});

it('makes concurrent repeated acceptance by the same actor idempotent', function (): void {
    $issued = app(InvitePartner::class)->execute(User::factory()->create(), new CoupleSetupData(CarbonImmutable::yesterday(), 'Europe/Minsk'));
    $guest = User::factory()->create();
    $results = (new ConcurrentInvitations)->run([
        ['actor' => $guest, 'token' => $issued->token],
        ['actor' => $guest, 'token' => $issued->token],
    ]);
    expect($results)->toBe(['accepted', 'accepted']);
    expect(CoupleMember::query()->count())->toBe(2);
});

it('enforces active membership uniqueness and the two-member capacity in MariaDB', function (): void {
    $issued = app(InvitePartner::class)->execute(User::factory()->create(), new CoupleSetupData(CarbonImmutable::yesterday(), 'Europe/Minsk'));
    $authorMembership = CoupleMember::query()->sole();
    $other = Couple::factory()->create();
    expect(fn () => CoupleMember::factory()->create(['couple_id' => $other->id, 'user_id' => $authorMembership->user_id, 'active_user_id' => $authorMembership->user_id]))->toThrow(QueryException::class);
    CoupleMember::factory()->create(['couple_id' => $issued->coupleId, 'slot' => 'second']);
    expect(fn () => DB::table('couple_members')->insert(['couple_id' => $issued->coupleId, 'user_id' => User::factory()->create()->id, 'slot' => 'third', 'joined_at' => now()]))->toThrow(QueryException::class);
    expect(fn () => CoupleMember::factory()->create(['couple_id' => $issued->coupleId, 'slot' => 'second']))->toThrow(QueryException::class);
});
