<?php

namespace Laravel\Vapor\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Octane\OctaneServiceProvider;
use Laravel\Vapor\Runtime\Handlers\OctaneHandler;
use Laravel\Vapor\Runtime\Octane\Octane;
use Laravel\Vapor\Tests\TestCase;
use Mockery;
use RuntimeException;

class OctaneHandlerTest extends TestCase
{
    protected function setUp(): void
    {
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

    public function test_request_body()
    {
        $handler = new OctaneHandler();

        Route::get('/', function () {
            return 'Hello World';
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
        ]);

        static::assertEquals('Hello World', $response->toApiGatewayFormat()['body']);
    }

    public function test_request_fires_events()
    {
        Event::fake([RequestReceived::class, RequestTerminated::class]);

        $handler = new OctaneHandler();

        Route::get('/', function () {
            return response('Hello World')->withHeaders([
                'Foo' => 'Bar',
            ]);
        });

        $handler->handle([
            'httpMethod' => 'GET',
        ]);

        Event::assertDispatched(RequestReceived::class);
        Event::assertDispatched(RequestTerminated::class);
    }

    public function test_request_headers()
    {
        $handler = new OctaneHandler();

        Route::get('/', function () {
            return response('Hello World')->withHeaders([
                'Foo' => 'Bar',
            ]);
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
        ]);

        static::assertArrayHasKey('Foo', $response->toApiGatewayFormat()['headers']);
        static::assertEquals('Bar', $response->toApiGatewayFormat()['headers']['Foo']);
    }

    public function test_request_status()
    {
        $handler = new OctaneHandler();

        Route::get('/', function () {
            throw new RuntimeException('Something wrong happened.');
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
        ]);

        static::assertEquals(500, $response->toApiGatewayFormat()['statusCode']);
    }

    public function test_each_request_have_its_own_app()
    {
        $handler = new OctaneHandler();

        Route::get('/bind', function () {
            app()->bind('counter', function () {
                return 1;
            });

            return app('counter');
        });

        Route::get('/bound', function () {
            return app()->bound('counter') ? 'bound' : 'not bound';
        });

        $bindResponse = $handler->handle([
            'httpMethod' => 'GET',
            'requestContext' => [
                'path' => '/bind',
            ],
        ]);

        $boundResponse = $handler->handle([
            'httpMethod' => 'GET',
            'requestContext' => [
                'path' => '/bound',
            ],
        ]);

        static::assertEquals('1', $bindResponse->toApiGatewayFormat()['body']);
        static::assertEquals('not bound', $boundResponse->toApiGatewayFormat()['body']);
    }
}
