<?php

namespace Laravel\Vapor\Tests\Feature\Commands;

if (! interface_exists(\Laravel\Octane\Contracts\Client::class)) {
    return;
}

use Laravel\Vapor\Tests\TestCase;

class OctaneStatusCommandTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['VAPOR_SSM_PATH'] = 'foo';

        parent::setUp();
    }

    public function test_when_octane_is_not_running()
    {
        $this->artisan('octane:status')
            ->assertSuccessful()
            ->expectsOutput('Octane server is not running.');
    }

    public function test_when_octane_is_running()
    {
        $_ENV['OCTANE_DATABASE_SESSION_TTL'] = 'false';

        $this->artisan('octane:status')
            ->assertSuccessful()
            ->expectsOutput('Octane server is running.');
    }

    protected function tearDown(): void
    {
        unset($_ENV['OCTANE_DATABASE_SESSION_TTL']);
        unset($_ENV['VAPOR_SSM_PATH']);

        parent::tearDown();
    }
}
