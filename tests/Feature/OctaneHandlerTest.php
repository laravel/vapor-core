<?php

namespace Laravel\Vapor\Tests\Feature;

if (\PHP_VERSION_ID < 80000) {
    return;
}

use Illuminate\Http\Request;
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
        if (! class_exists(\Laravel\Octane\Octane::class)) {
            $this->markTestSkipped('Requires Laravel Octane.');
        }

        parent::setUp();

        $_ENV['APP_VANITY_URL'] = 'https://127.0.0.1';
        $_ENV['LAMBDA_TASK_ROOT'] = __DIR__.'/../Fixtures';

        Octane::boot(app()->basePath());

        Octane::worker()->application()->register(OctaneServiceProvider::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        unset($_ENV['APP_VANITY_URL']);
        unset($_ENV['LAMBDA_TASK_ROOT']);
        unset($_ENV['VAPOR_MAINTENANCE_MODE_SECRET']);

        Octane::terminate();

        parent::tearDown();
    }

    public function test_response_body()
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

    public function test_response_fires_events()
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

    public function test_response_headers()
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

    public function test_response_status()
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
            'path' => '/bind',
        ]);

        $boundResponse = $handler->handle([
            'httpMethod' => 'GET',
            'path' => '/bound',
        ]);

        static::assertEquals('1', $bindResponse->toApiGatewayFormat()['body']);
        static::assertEquals('not bound', $boundResponse->toApiGatewayFormat()['body']);
    }

    public function test_response_cookies()
    {
        $handler = new OctaneHandler();

        Route::get('/', function () {
            return response('Hello World')->cookie('cookie-key', 'cookie-value', 10);
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
            'path' => '/',
        ]);

        $setCookie = $response->toApiGatewayFormat()['headers']['set-cookie'];

        static::assertStringStartsWith('cookie-key=cookie-value;', $setCookie);
    }

    public function test_robots_header_is_set()
    {
        $handler = new OctaneHandler();

        $_ENV['APP_VANITY_URL'] = 'https://127.0.0.1';

        Route::get('/', function () {
            return 'Hello World';
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
            'path' => '/',
        ]);

        $robotsTag = $response->toApiGatewayFormat()['headers']['X-Robots-Tag'];

        static::assertEquals('noindex, nofollow', $robotsTag);
    }

    public function test_maintenance_mode()
    {
        $handler = new OctaneHandler();

        $_ENV['VAPOR_MAINTENANCE_MODE'] = 'true';
        $_ENV['APP_VANITY_URL'] = 'production.com';

        Route::get('/', function () {
            return 'Hello World';
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
            'path' => '/',
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        self::assertEquals('application/json', $response->toApiGatewayFormat()['headers']['Content-Type']);
        self::assertEquals(['hello' => 'world'], json_decode($response->toApiGatewayFormat()['body'], true));
    }

    public function test_request_body()
    {
        $handler = new OctaneHandler();

        Route::get('/{content}', function ($content) {
            return $content;
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
            'path' => '/hello-world',
        ]);

        static::assertEquals('hello-world', $response->toApiGatewayFormat()['body']);
    }

    public function test_request_cookies()
    {
        $handler = new OctaneHandler();

        Route::get('/', function (Request $request) {
            return $request->cookies->all();
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
            'path' => '/',
            'headers' => [
                'cookie' => 'XSRF-TOKEN=token_value',
            ],
        ]);

        static::assertEquals(
            ['XSRF-TOKEN' => 'token_value'],
            json_decode($response->toApiGatewayFormat()['body'], true)
        );
    }

    public function test_request_query_string()
    {
        $handler = new OctaneHandler();

        Route::get('/', function (Request $request) {
            return $request->getQueryString();
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
            'path' => '/?foo=bar',
            'headers' => [
                'cookie' => 'XSRF-TOKEN=token_value',
            ],
        ]);

        static::assertEquals('foo=bar', $response->toApiGatewayFormat()['body']);
    }

    public function test_request_headers()
    {
        $handler = new OctaneHandler();

        Route::get('/', function (Request $request) {
            return $request->headers->all();
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
            'path' => '/',
            'headers' => [
                'X-Xsrf-Token' => 'my-token',
            ],
        ]);

        $body = $response->toApiGatewayFormat()['body'];

        static::assertEquals(['my-token'], json_decode($body, true)['x-xsrf-token']);
    }
}
