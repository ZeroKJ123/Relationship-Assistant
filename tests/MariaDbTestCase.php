<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Tests\Support\MariaDbEnvironment;

abstract class MariaDbTestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        (new MariaDbEnvironment)->assertIsolated($app);

        return $app;
    }
}
