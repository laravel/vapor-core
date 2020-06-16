<?php

namespace Laravel\Vapor\Tests;

use Laravel\Vapor\Runtime\Http\Middleware\EnsureBinaryEncoding;
use Mockery;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\TestCase;
use Laravel\Vapor\Runtime\HttpKernel;

class HttpKernelTest extends TestCase
{
    public function tearDown() : void
    {
        Mockery::close();
    }


    public function test_request_can_be_handled()
    {
        // $app = Mockery::mock('Illuminate\Foundation\Application');

        // $app->shouldReceive('useStoragePath')->once()->with('/tmp/storage');
        // $app->shouldReceive('storagePath')->andReturn('/tmp/storage');
        // $app->shouldReceive('handle')->andReturn($mockResponse = new Response('Hello World'));
        // $app->shouldReceive('terminate')->once();

        // $handler = new HttpKernel($app);
        // $response = $handler->handle(Request::create('/', 'GET'));

        // $this->assertEquals($mockResponse, $response);
    }


    public function test_should_send_maintenance_mode_response_when_enabled_and_on_non_vanity_domain()
    {
        $_ENV['APP_VANITY_URL'] = 'https://something.com';
        $_ENV['VAPOR_MAINTENANCE_MODE'] = 'true';

        $this->assertTrue(HttpKernel::shouldSendMaintenanceModeResponse(Request::create('/', 'GET')));
        $this->assertFalse(HttpKernel::shouldSendMaintenanceModeResponse(Request::create('http://something.com', 'GET')));

        unset($_ENV['APP_VANITY_URL']);
        unset($_ENV['VAPOR_MAINTENANCE_MODE']);

        $this->assertFalse(HttpKernel::shouldSendMaintenanceModeResponse(Request::create('/', 'GET')));
        $this->assertFalse(HttpKernel::shouldSendMaintenanceModeResponse(Request::create('http://something.com', 'GET')));
    }

    public function test_should_send_isbase64encode_on_binary_response()
    {
        $response = new Response('ok', 200);
        $this->assertFalse(EnsureBinaryEncoding::isBase64EncodingRequired($response));

        $response = new Response("{}", 200, [
            'Content-Type' => 'application/json',
        ]);
        $this->assertFalse(EnsureBinaryEncoding::isBase64EncodingRequired($response));

        $response = new Response("{}", 200, [
            'Content-Type' => 'application/vnd.api+json; charset=UTF-8',
        ]);
        $this->assertFalse(EnsureBinaryEncoding::isBase64EncodingRequired($response));

        $response = new Response("*", 200, [
            'Content-Type' => 'application/octet-stream',
        ]);
        $this->assertTrue(EnsureBinaryEncoding::isBase64EncodingRequired($response));

        $response = new Response('*', 200, [
            'Content-Type' => 'image/png',
        ]);
        $this->assertTrue(EnsureBinaryEncoding::isBase64EncodingRequired($response));
    }
}
