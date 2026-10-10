<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function reloadPersistentBrowserSession(): void
{
    Auth::forgetGuards();
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
}

beforeEach(function (): void {
    $repository = Env::getRepository();
    $previousLifetime = $repository->get('SESSION_LIFETIME');
    $previousExpireOnClose = $repository->get('SESSION_EXPIRE_ON_CLOSE');
    $repository->clear('SESSION_LIFETIME');
    $repository->clear('SESSION_EXPIRE_ON_CLOSE');
    try {
        $sessionConfiguration = require config_path('session.php');
    } finally {
        if ($previousLifetime !== null) {
            $repository->set('SESSION_LIFETIME', $previousLifetime);
        }
        if ($previousExpireOnClose !== null) {
            $repository->set('SESSION_EXPIRE_ON_CLOSE', $previousExpireOnClose);
        }
    }
    config()->set('session.lifetime', $sessionConfiguration['lifetime']);
    config()->set('session.expire_on_close', $sessionConfiguration['expire_on_close']);
    config()->set('session.driver', 'database');
    config()->set('session.lottery', [0, 100]);
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    $this->withoutVite();
    $this->travelTo(now()->startOfSecond());
});

it('keeps a browser logged in and refreshes its thirty day inactivity deadline', function (): void {
    $user = User::factory()->create(['login' => 'session_test', 'password' => 'safePassword123']);
    $cookieName = config()->string('session.cookie');
    $login = $this->post(route('login.store'), ['login' => $user->login, 'password' => 'safePassword123'])
        ->assertRedirect(route('profile.show'));
    $cookie = $login->getCookie($cookieName);
    expect($cookie)->not->toBeNull();
    expect($cookie->getExpiresTime())->toBe(now()->addDays(30)->getTimestamp());
    expect($cookie->isHttpOnly())->toBeTrue();
    expect($login->getCookie(Auth::guard()->getRecallerName(), false))->toBeNull();

    $this->travel(29)->days();
    reloadPersistentBrowserSession();
    $returnVisit = $this->withCookie($cookieName, $cookie->getValue())->get(route('profile.show'))->assertOk();
    $this->assertAuthenticatedAs($user);
    $renewedCookie = $returnVisit->getCookie($cookieName);
    expect($renewedCookie->getExpiresTime())->toBe(now()->addDays(30)->getTimestamp());
    expect((int) DB::table('sessions')->where('id', $renewedCookie->getValue())->value('last_activity'))->toBe(now()->getTimestamp());

    $this->travel(29)->days();
    reloadPersistentBrowserSession();
    $this->withCookie($cookieName, $renewedCookie->getValue())->get(route('profile.show'))->assertOk();
    $this->assertAuthenticatedAs($user);
});

it('rejects a saved browser session after thirty days without activity', function (): void {
    $user = User::factory()->create(['login' => 'session_test', 'password' => 'safePassword123']);
    $cookieName = config()->string('session.cookie');
    $login = $this->post(route('login.store'), ['login' => $user->login, 'password' => 'safePassword123']);
    $cookie = $login->getCookie($cookieName);

    $this->travel(30)->days();
    $this->travel(1)->seconds();
    reloadPersistentBrowserSession();
    $this->withCookie($cookieName, $cookie->getValue())->get(route('profile.show'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('invalidates a persistent browser session immediately on explicit logout', function (): void {
    $user = User::factory()->create(['login' => 'session_test', 'password' => 'safePassword123']);
    $cookieName = config()->string('session.cookie');
    $login = $this->post(route('login.store'), ['login' => $user->login, 'password' => 'safePassword123']);
    $cookie = $login->getCookie($cookieName);
    $this->withCookie($cookieName, $cookie->getValue())->post(route('logout'))->assertRedirect(route('home'));

    reloadPersistentBrowserSession();
    $this->withCookie($cookieName, $cookie->getValue())->get(route('profile.show'))->assertRedirect(route('login'));
    $this->assertGuest();
});
