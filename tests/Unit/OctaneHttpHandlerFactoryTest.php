<?php

namespace Laravel\Vapor\Tests\Unit;

use Laravel\Vapor\Runtime\Handlers\OctaneHandler;
use Laravel\Vapor\Runtime\Handlers\UnknownEventHandler;
use Laravel\Vapor\Runtime\Handlers\WarmerHandler;
use Laravel\Vapor\Runtime\Handlers\WarmerPingHandler;
use Laravel\Vapor\Runtime\Octane\OctaneHttpHandlerFactory;
use Mockery;
use PHPUnit\Framework\TestCase;

class OctaneHttpHandlerFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        if (! class_exists('Laravel\Octane\Octane')) {
            $this->markTestSkipped('Requires Laravel Octane.');
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Mockery::close();
    }

    public function test_resolves_warmer()
    {
        $handler = OctaneHttpHandlerFactory::make([
            'vaporWarmer' => true,
        ]);

        static::assertInstanceOf(WarmerHandler::class, $handler);
    }

    public function test_resolves_warmer_ping()
    {
        $handler = OctaneHttpHandlerFactory::make([
            'vaporWarmerPing' => true,
        ]);

        static::assertInstanceOf(WarmerPingHandler::class, $handler);
    }

    public function test_resolves_octane()
    {
        $handler = OctaneHttpHandlerFactory::make([
            'httpMethod' => true,
        ]);

        static::assertInstanceOf(OctaneHandler::class, $handler);
    }

    public function test_resolves_unknown()
    {
        $handler = OctaneHttpHandlerFactory::make([
            // ..
        ]);

        static::assertInstanceOf(UnknownEventHandler::class, $handler);
    }
}
