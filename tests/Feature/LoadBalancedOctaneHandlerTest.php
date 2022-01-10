<?php

namespace Laravel\Vapor\Tests\Feature;

if (! interface_exists(\Laravel\Octane\Contracts\Client::class)) {
    return;
}

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Octane\OctaneServiceProvider;
use Laravel\Vapor\Runtime\Handlers\LoadBalancedOctaneHandler;
use Laravel\Vapor\Runtime\Octane\Octane;
use Laravel\Vapor\Tests\TestCase;
use Mockery;
use RuntimeException;

class LoadBalancedOctaneHandlerTest extends TestCase
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
        $handler = new LoadBalancedOctaneHandler();

        Route::get('/', function () {
            return 'Hello World';
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
        ]);

        static::assertEquals('Hello World', $response->toApiGatewayFormat()['body']);
    }

    public function test_invalid_uri()
    {
        $handler = new LoadBalancedOctaneHandler();

        Route::get('/', function () {
            return 'Hello World';
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
            'path' => '/////foo',
        ]);

        static::assertEquals('Hello World', $response->toApiGatewayFormat()['body']);
    }

    public function test_response_fires_events()
    {
        Event::fake([RequestReceived::class, RequestTerminated::class]);

        $handler = new LoadBalancedOctaneHandler();

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

    public function test_response_file()
    {
        $handler = new LoadBalancedOctaneHandler();

        Route::get('/', function (Request $request) {
            return response()->file(__DIR__.'/../Fixtures/asset.js', [
                'Content-Type' => 'text/javascript',
            ]);
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
            'path' => '/',
        ]);

        static::assertEquals(['text/javascript'], $response->toApiGatewayFormat()['multiValueHeaders']['Content-Type']);
        static::assertEquals("console.log();\n", $response->toApiGatewayFormat()['body']);
    }

    public function test_response_download()
    {
        $handler = new LoadBalancedOctaneHandler();

        Route::get('/', function (Request $request) {
            return response()->download(__DIR__.'/../Fixtures/asset.js');
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
            'path' => '/',
        ]);

        static::assertEquals(['attachment; filename=asset.js'], $response->toApiGatewayFormat()['multiValueHeaders']['Content-Disposition']);
        static::assertEquals("console.log();\n", $response->toApiGatewayFormat()['body']);
    }

    public function test_response_headers()
    {
        $handler = new LoadBalancedOctaneHandler();

        Route::get('/', function () {
            return response('Hello World')->withHeaders([
                'Foo' => 'Bar',
            ]);
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
        ]);

        static::assertArrayHasKey('Foo', $response->toApiGatewayFormat()['multiValueHeaders']);
        static::assertEquals(['Bar'], $response->toApiGatewayFormat()['multiValueHeaders']['Foo']);
    }

    public function test_response_status()
    {
        $handler = new LoadBalancedOctaneHandler();

        Route::get('/', function () {
            throw new RuntimeException('Something wrong happened.');
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
        ]);

        static::assertEquals(500, $response->toApiGatewayFormat()['statusCode']);
    }

    public function test_each_response_have_its_own_app()
    {
        $handler = new LoadBalancedOctaneHandler();

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
            'path' => 'bind',
        ]);

        $boundResponse = $handler->handle([
            'httpMethod' => 'GET',
            'path' => 'bound',
        ]);

        static::assertEquals('1', $bindResponse->toApiGatewayFormat()['body']);
        static::assertEquals('not bound', $boundResponse->toApiGatewayFormat()['body']);
    }

    public function test_response_cookies()
    {
        $handler = new LoadBalancedOctaneHandler();

        Route::get('/', function () {
            return response('Hello World')->cookie('cookie-key', 'cookie-value', 10);
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
            'path' => '/',
        ]);

        $setCookie = $response->toApiGatewayFormat()['multiValueHeaders']['Set-Cookie'];

        static::assertStringStartsWith('cookie-key=cookie-value;', $setCookie[0]);
    }

    public function test_robots_header_is_set()
    {
        $handler = new LoadBalancedOctaneHandler();

        Route::get('/', function () {
            return 'Hello World';
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
            'path' => '/',
        ]);

        $robotsTag = $response->toApiGatewayFormat()['multiValueHeaders']['X-Robots-Tag'];

        static::assertEquals(['noindex, nofollow'], $robotsTag);
    }

    public function test_maintenance_mode()
    {
        $handler = new LoadBalancedOctaneHandler();

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

        static::assertEquals(['application/json'], $response->toApiGatewayFormat()['multiValueHeaders']['Content-Type']);
        static::assertEquals(['message' => 'We are currently down for maintenance.'], json_decode($response->toApiGatewayFormat()['body'], true));
    }

    public function test_request_body()
    {
        $handler = new LoadBalancedOctaneHandler();

        Route::put('/', function (Request $request) {
            return $request->all();
        });

        $response = $handler->handle([
            'httpMethod' => 'POST',
            'path' => '/',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => <<<'EOF'
{"_method":"PUT","name":"nuno","email":"nuno@laravel.com"}
EOF
        ]);

        static::assertEquals([
            '_method' => 'PUT',
            'name' => 'nuno',
            'email' => 'nuno@laravel.com',
        ], json_decode($response->toApiGatewayFormat()['body'], true));
    }

    public function test_request_file_uploads()
    {
        $handler = new LoadBalancedOctaneHandler();

        Route::put('/', function (Request $request) {
            return array_merge($request->all(), [
                'filename' => $request->file('file')->getClientOriginalName(),
                'file' => $request->file('file')->getContent(),
            ]);
        });

        $response = $handler->handle([
            'httpMethod' => 'POST',
            'path' => '/',
            'headers' => [
                'Content-Type' => 'multipart/form-data; boundary=---------------------------317050813134112680482597024243',
            ],
            'body' => <<<'EOF'
-----------------------------317050813134112680482597024243
Content-Disposition: form-data; name="_method"

PUT
-----------------------------317050813134112680482597024243
Content-Disposition: form-data; name="name"

nuno
-----------------------------317050813134112680482597024243
Content-Disposition: form-data; name="email"

nuno@laravel.com
-----------------------------317050813134112680482597024243
Content-Disposition: form-data; name="file"; filename="my_uploaded.txt"
Content-Type: text/plain

foo

-----------------------------317050813134112680482597024243--
EOF
        ]);

        static::assertEquals([
            '_method' => 'PUT',
            'name' => 'nuno',
            'email' => 'nuno@laravel.com',
            'filename' => 'my_uploaded.txt',
            'file' => 'foo',
        ], json_decode($response->toApiGatewayFormat()['body'], true));
    }

    public function test_request_cookies()
    {
        $handler = new LoadBalancedOctaneHandler();

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
        $handler = new LoadBalancedOctaneHandler();

        Route::get('/', function (Request $request) {
            return $request->getQueryString();
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
            'path' => '/',
            'multiValueQueryStringParameters' => [
                'foo' => ['bar'],
            ],
            'headers' => [
                'cookie' => 'XSRF-TOKEN=token_value',
            ],
        ]);

        static::assertEquals('foo=bar', $response->toApiGatewayFormat()['body']);
    }

    public function test_request_query_params()
    {
        $handler = new LoadBalancedOctaneHandler();

        Route::get('/', function (Request $request) {
            return $request->query();
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
            'path' => '/',
            'multiValueQueryStringParameters' => [
                'foo' => ['bar'],
            ],
            'headers' => [
                'cookie' => 'XSRF-TOKEN=token_value',
            ],
        ]);

        static::assertEquals(['foo' => 'bar'], json_decode($response->toApiGatewayFormat()['body'], true));
    }

    public function test_request_headers()
    {
        $handler = new LoadBalancedOctaneHandler();

        Route::get('/', function (Request $request) {
            return $request->headers->all();
        });

        $response = $handler->handle([
            'httpMethod' => 'GET',
            'path' => '/',
            'headers' => [
                'My-Header' => 'my-header-value',
            ],
        ]);

        $body = $response->toApiGatewayFormat()['body'];

        static::assertEquals(['my-header-value'], json_decode($body, true)['my-header']);
    }

    public function test_maintenance_mode_with_valid_bypass_cookie()
    {
        $octane = new class() extends Octane
        {
            public static function hasValidBypassCookie($request, $secret)
            {
                return true;
            }
        };

        $handler = new class() extends LoadBalancedOctaneHandler
        {
            public function request($event)
            {
                return parent::request($event);
            }

            public function response($response)
            {
                return parent::response($response);
            }
        };

        $_ENV['VAPOR_MAINTENANCE_MODE'] = 'true';
        $_ENV['APP_VANITY_URL'] = 'production.com';
        $_ENV['VAPOR_MAINTENANCE_MODE_SECRET'] = 'my-secret';

        Route::get('/', function () {
            return 'Hello World';
        });

        $response = $handler->response($octane::handle($handler->request([
            'httpMethod' => 'GET',
            'path' => '/',
        ])));

        static::assertEquals('Hello World', $response->toApiGatewayFormat()['body']);
    }

    public function test_maintenance_mode_with_invalid_bypass_cookie()
    {
        $handler = new LoadBalancedOctaneHandler();

        $_ENV['VAPOR_MAINTENANCE_MODE'] = 'true';
        $_ENV['APP_VANITY_URL'] = 'production.com';
        $_ENV['VAPOR_MAINTENANCE_MODE_SECRET'] = 'my-secret';

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

        static::assertEquals(['application/json'], $response->toApiGatewayFormat()['multiValueHeaders']['Content-Type']);
        static::assertEquals(['message' => 'We are currently down for maintenance.'], json_decode($response->toApiGatewayFormat()['body'], true));
    }
}
