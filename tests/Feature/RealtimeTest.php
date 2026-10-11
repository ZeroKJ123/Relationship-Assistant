<?php

declare(strict_types=1);

use App\Events\ApplicationUpdated;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(DatabaseMigrations::class);

it('broadcasts business mutations only after commit and discards rollbacks', function (): void {
    config(['realtime.enabled' => true]);
    Event::fake([ApplicationUpdated::class]);
    DB::beginTransaction();
    User::factory()->create();
    Event::assertNotDispatched(ApplicationUpdated::class);
    DB::rollBack();
    Event::assertNotDispatched(ApplicationUpdated::class);
    DB::transaction(fn (): User => User::factory()->create());
    Event::assertDispatched(ApplicationUpdated::class);
    $event = new ApplicationUpdated;
    expect($event->broadcastWith())->toBe([])->and($event->broadcastOn()->name)->toBe('private-application.updates');
});

it('requires authentication to subscribe and rejects other channel names', function (): void {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test', 'broadcasting.connections.reverb.secret' => 'test', 'broadcasting.connections.reverb.app_id' => 'test']);
    require base_path('routes/channels.php');
    $this->postJson('/broadcasting/auth', ['channel_name' => 'private-application.updates', 'socket_id' => '1.2'])->assertUnauthorized();
    $this->asUser(User::factory()->create())->postJson('/broadcasting/auth', ['channel_name' => 'private-application.updates', 'socket_id' => '1.2'])->assertOk()->assertJsonStructure(['auth']);
    $this->postJson('/broadcasting/auth', ['channel_name' => 'private-other', 'socket_id' => '1.2'])->assertForbidden();
});

it('does not broadcast reads or technical writes', function (): void {
    $user = User::factory()->create();
    config(['realtime.enabled' => true]);
    Event::fake([ApplicationUpdated::class]);
    User::query()->find($user->id);
    DB::table('sessions')->insert(['id' => 'test-session', 'payload' => 'test', 'last_activity' => time()]);
    Event::assertNotDispatched(ApplicationUpdated::class);
    User::query()->whereKey($user->id)->update(['name' => 'Updated name']);
    Event::assertDispatched(ApplicationUpdated::class);
});
