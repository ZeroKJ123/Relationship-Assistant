<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Tests\Support\TestEnvironment;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function asUser(User $user): static
    {
        // A new browser session when a scenario switches to another participant.
        $this->flushSession();

        return $this->actingAs($user);
    }

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        (new TestEnvironment)->assertIsolated($app);
        $app->make('config')->set('database.connections', [
            'sqlite' => $app->make('config')->get('database.connections.sqlite'),
        ]);

        return $app;
    }
}
