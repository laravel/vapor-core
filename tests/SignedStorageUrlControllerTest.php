<?php

namespace Laravel\Vapor\Tests;

use GuzzleHttp\Client;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Gate;

class SignedStorageUrlControllerTest extends TestCase
{
    public function setUp() : void
    {
        parent::setUp();

        $_ENV['AWS_BUCKET'] = 'laravel-s3-test-1';
    }


    public function test_controller_returns_signed_urls()
    {
        Gate::define('uploadFiles', function ($user = null, $bucket) {
            return true;
        });

        $response = $this->withoutExceptionHandling()->json('POST', '/vapor/signed-storage-url?content_type=text/plain');

        $response->assertStatus(201);
        $this->assertTrue(is_string($response->original['uuid']));
        $this->assertTrue(is_string($response->original['key']));
        $this->assertTrue(is_string($response->original['url']));
        $this->assertTrue(is_array($response->original['headers']));
        $this->assertEquals('text/plain', $response->original['headers']['Content-Type']);

        // $url = $response->original['url'];
        // $headers = $response->original['headers'];

        // $response = (new Client)->request('PUT', $url, array_filter([
        //     'body' => file_get_contents(__FILE__),
        //     'headers' => $headers,
        // ]));
    }

    public function test_aws_url_environmental_variable_is_used()
    {
        $_ENV['AWS_URL'] = 'http://custom-url';
        Gate::define('uploadFiles', function ($user = null, $bucket) {
            return true;
        });

        $response = $this->withoutExceptionHandling()->json('POST', '/vapor/signed-storage-url?content_type=text/plain');
        $response->assertStatus(201);
        $this->assertStringContainsString('laravel-s3-test-1.custom-url', $response->original['url']);
    }


    public function test_cant_retrieve_signed_urls_without_proper_environment_variables()
    {
        Gate::define('uploadFiles', function ($user = null, $bucket) {
            return true;
        });

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing environment variables');

        unset($_ENV['AWS_BUCKET']);

        $response = $this->withoutExceptionHandling()->json('POST', '/vapor/signed-storage-url');
    }


    public function test_cant_retrieve_signed_urls_if_not_authenticated()
    {
        Gate::define('uploadFiles', function ($user, $bucket) {
            return true;
        });

        $response = $this->json('POST', '/vapor/signed-storage-url?content_type=text/plain');

        $response->assertStatus(403);
    }


    public function test_cant_retrieve_signed_urls_if_not_authorized()
    {
        Gate::define('uploadFiles', function ($user = null, $bucket) {
            return false;
        });

        $response = $this->json('POST', '/vapor/signed-storage-url?content_type=text/plain');

        $response->assertStatus(403);
    }


    /**
     * Get the package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return ['Laravel\Vapor\VaporServiceProvider'];
    }
}
