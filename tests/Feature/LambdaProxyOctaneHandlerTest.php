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
use Laravel\Vapor\Runtime\Handlers\OctaneHandler;
use Laravel\Vapor\Runtime\Octane\Octane;
use Laravel\Vapor\Tests\TestCase;
use Mockery;
use RuntimeException;

class LambdaProxyOctaneHandlerTest extends TestCase
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
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                ],
            ],
        ]);

        static::assertEquals('Hello World', $response->toApiGatewayFormat()['body']);
    }

    public function test_invalid_uri()
    {
        $handler = new OctaneHandler();

        Route::get('/', function () {
            return 'Hello World';
        });

        $response = $handler->handle([
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'path' => '/////foo',
                ],
            ],
        ]);

        static::assertEquals('Hello World', $response->toApiGatewayFormat()['body']);
    }

    public function test_response_file()
    {
        $handler = new OctaneHandler();

        Route::get('/', function (Request $request) {
            return response()->file(__DIR__.'/../Fixtures/asset.js', [
                'Content-Type' => 'text/javascript; charset=UTF-8',
            ]);
        });

        $response = $handler->handle([
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'path' => '/',
                ],
            ],
        ]);

        static::assertEquals('text/javascript; charset=UTF-8', $response->toApiGatewayFormat()['headers']['Content-Type']);
        static::assertEquals("console.log();\n", $response->toApiGatewayFormat()['body']);
    }

    public function test_response_download()
    {
        $handler = new OctaneHandler();

        Route::get('/', function (Request $request) {
            return response()->download(__DIR__.'/../Fixtures/asset.js');
        });

        $response = $handler->handle([
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                ],
            ],
        ]);

        static::assertEquals('attachment; filename=asset.js', $response->toApiGatewayFormat()['headers']['Content-Disposition']);
        static::assertEquals("console.log();\n", $response->toApiGatewayFormat()['body']);
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
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                ],
            ],
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
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                ],
            ],
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
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                ],
            ],
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
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'path' => '/bind',
                ],
            ],
        ]);

        $boundResponse = $handler->handle([
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'path' => '/bound',
                ],
            ],
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
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'path' => '/',
                ],
            ],
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
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'path' => '/',
                ],
            ],
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
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'path' => '/',
                ],
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        static::assertEquals('application/json', $response->toApiGatewayFormat()['headers']['Content-Type']);
        static::assertEquals(['message' => 'We are currently down for maintenance.'], json_decode($response->toApiGatewayFormat()['body'], true));
    }

    public function test_request_body()
    {
        $handler = new OctaneHandler();

        Route::put('/', function (Request $request) {
            return $request->all();
        });

        $response = $handler->handle([
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'POST',
                    'path' => '/',
                ],
            ],
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

    public function test_request_form_url_encoded_without_inline_input_method()
    {
        $handler = new OctaneHandler();

        Route::put('/', function (Request $request) {
            return $request->all();
        });

        $response = $handler->handle([
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'PUT',
                    'path' => '/',
                ],
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
            ],
            'body' => <<<'EOF'
name=nuno&email=nuno@laravel.com
EOF
        ]);

        static::assertEquals([
            'name' => 'nuno',
            'email' => 'nuno@laravel.com',
        ], json_decode($response->toApiGatewayFormat()['body'], true));
    }

    public function test_request_cookies()
    {
        $handler = new OctaneHandler();

        Route::get('/', function (Request $request) {
            return $request->cookies->all();
        });

        $response = $handler->handle([
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'path' => '/',
                ],
            ],
            'cookies' => [
                'XSRF-TOKEN=token_value',
            ],
        ]);

        static::assertEquals(
            ['XSRF-TOKEN' => 'token_value'],
            json_decode($response->toApiGatewayFormat()['body'], true)
        );
    }

    public function test_request_ignores_invalid_cookies()
    {
        $handler = new OctaneHandler();

        Route::get('/', function (Request $request) {
            return $request->cookies->all();
        });

        $response = $handler->handle([
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'path' => '/',
                ],
            ],
            'cookies' => [
                'cookieKey1',
                'cookieKey2=cookieValue2',
            ],
        ]);

        static::assertEquals(
            ['cookieKey2' => 'cookieValue2'],
            json_decode($response->toApiGatewayFormat()['body'], true)
        );
    }

    public function test_request_file_uploads()
    {
        $handler = new OctaneHandler();

        Route::put('/', function (Request $request) {
            return array_merge($request->all(), [
                'filename' => $request->file('file')->getClientOriginalName(),
                'file' => $request->file('file')->getContent(),
            ]);
        });

        $response = $handler->handle([
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'POST',
                    'path' => '/',
                ],
            ],
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

    public function test_request_file_uploads_without_inline_input_method()
    {
        $handler = new OctaneHandler();

        Route::put('/', function (Request $request) {
            return array_merge($request->all(), [
                'filename' => $request->file('file')->getClientOriginalName(),
                'file' => $request->file('file')->getContent(),
            ]);
        });

        $response = $handler->handle([
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'PUT',
                    'path' => '/',
                ],
            ],
            'headers' => [
                'Content-Type' => 'multipart/form-data; boundary=---------------------------317050813134112680482597024243',
            ],
            'body' => <<<'EOF'
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
            'name' => 'nuno',
            'email' => 'nuno@laravel.com',
            'filename' => 'my_uploaded.txt',
            'file' => 'foo',
        ], json_decode($response->toApiGatewayFormat()['body'], true));
    }

    public function test_request_query_string()
    {
        $handler = new OctaneHandler();

        Route::get('/', function (Request $request) {
            return $request->getQueryString();
        });

        $response = $handler->handle([
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'path' => '/',
                ],
            ],
            'queryStringParameters' => [
                'param1' => 'value1',
                'param2' => 'value1,value2',
            ],
            'headers' => [
                'cookie' => 'XSRF-TOKEN=token_value',
            ],
        ]);

        static::assertEquals('param1=value1&param2[0]=value1&param2[1]=value2', urldecode($response->toApiGatewayFormat()['body']));
    }

    public function test_request_query_params()
    {
        $handler = new OctaneHandler();

        Route::get('/', function (Request $request) {
            return $request->query();
        });

        $response = $handler->handle([
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'path' => '/',
                ],
            ],
            'queryStringParameters' => [
                'param1' => 'value1',
                'param2' => 'value1,value2',
            ],
            'headers' => [
                'cookie' => 'XSRF-TOKEN=token_value',
            ],
        ]);

        static::assertEquals([
            'param1' => 'value1',
            'param2' => ['value1', 'value2'],
        ], json_decode($response->toApiGatewayFormat()['body'], true));
    }

    public function test_request_headers()
    {
        $handler = new OctaneHandler();

        Route::get('/', function (Request $request) {
            return $request->headers->all();
        });

        $response = $handler->handle([
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'path' => '/',
                ],
            ],
            'path' => '/',
            'headers' => [
                'X-Xsrf-Token' => 'my-token',
            ],
        ]);

        $body = $response->toApiGatewayFormat()['body'];

        static::assertEquals(['my-token'], json_decode($body, true)['x-xsrf-token']);
    }

    public function test_request_multi_headers()
    {
        $handler = new OctaneHandler();

        Route::get('/', function (Request $request) {
            return $request->headers->all();
        });

        $response = $handler->handle([
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'path' => '/',
                ],
            ],
            'headers' => [
                'X-Xsrf-Token' => 'my-token',
                'X-Multi-Header' => 'value1,value2',
            ],
        ]);

        $body = $response->toApiGatewayFormat()['body'];

        static::assertEquals(['my-token'], json_decode($body, true)['x-xsrf-token']);
        static::assertEquals(['value1,value2'], json_decode($body, true)['x-multi-header']);
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

        $handler = new class() extends OctaneHandler
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
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'path' => '/',
                ],
            ],
        ])));

        static::assertEquals('Hello World', $response->toApiGatewayFormat()['body']);
    }

    public function test_maintenance_mode_with_invalid_bypass_cookie()
    {
        $handler = new OctaneHandler();

        $_ENV['VAPOR_MAINTENANCE_MODE'] = 'true';
        $_ENV['APP_VANITY_URL'] = 'production.com';
        $_ENV['VAPOR_MAINTENANCE_MODE_SECRET'] = 'my-secret';

        Route::get('/', function () {
            return 'Hello World';
        });

        $response = $handler->handle([
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'path' => '/',
                ],
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        static::assertEquals('application/json', $response->toApiGatewayFormat()['headers']['Content-Type']);
        static::assertEquals(['message' => 'We are currently down for maintenance.'], json_decode($response->toApiGatewayFormat()['body'], true));
    }
}
