<?php

declare(strict_types=1);
use Tests\MariaDbTestCase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

pest()->extend(MariaDbTestCase::class)->in('Integration');
