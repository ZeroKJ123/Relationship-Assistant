<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates https links behind a trusted https proxy', function (): void {
    config(['trustedproxy.proxies' => '127.0.0.1']);

    $this->get(route('login'), ['X-Forwarded-Proto' => 'https'])->assertOk();

    expect(route('login'))->toStartWith('https://');
});

it('ignores forwarded headers from an untrusted sender', function (): void {
    config(['trustedproxy.proxies' => '10.0.0.1']);

    $this->get(route('login'), ['X-Forwarded-Proto' => 'https'])->assertOk();

    expect(route('login'))->toStartWith('http://');
});

it('publishes separate realtime ports for plain and secure pages', function (): void {
    config(['realtime.enabled' => true, 'realtime.public_port' => 8089, 'realtime.secure_public_port' => 443]);

    $this->asUser(User::factory()->create())->get(route('home'))
        ->assertOk()
        ->assertSee('<meta name="realtime-port" content="8089">', false)
        ->assertSee('<meta name="realtime-secure-port" content="443">', false);
});
