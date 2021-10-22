<?php

namespace Laravel\Vapor\Tests\Feature;

if (! interface_exists(\Laravel\Octane\Contracts\Client::class)) {
    return;
}

use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Laravel\Octane\Events\WorkerErrorOccurred;
use Laravel\Octane\OctaneServiceProvider;
use Laravel\Octane\RequestContext;
use Laravel\Vapor\Runtime\Octane\Octane;
use Laravel\Vapor\Tests\TestCase;
use Mockery;

class OctaneTest extends TestCase
{
    protected function setUp(): void
    {
        if (! class_exists(\Laravel\Octane\Octane::class)) {
            $this->markTestSkipped('Requires Laravel Octane.');
        }

        parent::setUp();

        Octane::boot(app()->basePath());

        Octane::worker()->application()->register(OctaneServiceProvider::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        Octane::terminate();

        parent::tearDown();
    }

    public function test_on_error()
    {
        Event::fake([WorkerErrorOccurred::class]);

        $worker = Octane::worker();

        $reflection = new \ReflectionClass($worker);
        $method = tap($reflection->getMethod('handleWorkerError'))->setAccessible(true);

        $exception = new Exception('Something wrong happened');

        $exceptionHandler = Mockery::mock(ExceptionHandler::class);
        $exceptionHandler->shouldReceive('report')->with($exception);

        $app = Mockery::mock($worker->application());
        $app->shouldReceive('make')->with(ExceptionHandler::class)->andReturn($exceptionHandler);

        $method->invoke(
            $worker,
            $exception,
            $app,
            new Request(),
            new RequestContext(),
            false
        );

        Event::assertDispatched(WorkerErrorOccurred::class);
    }

    public function test_server_software()
    {
        self::assertSame('vapor', $_ENV['SERVER_SOFTWARE']);
        self::assertSame('vapor', $_SERVER['SERVER_SOFTWARE']);
    }
}
