<?php

namespace Laravel\Vapor\Tests;

use Laravel\Octane\OctaneServiceProvider;
use Mockery;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [OctaneServiceProvider::class];
    }
}
