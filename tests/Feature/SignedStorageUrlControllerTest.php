<?php

namespace Laravel\Vapor\Tests\Feature;

use GuzzleHttp\Client;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;

class SignedStorageUrlControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $this->markTestSkipped('Requires an AWS account.');

        parent::setUp();

        $_ENV['AWS_BUCKET'] = 'laravel-s3-test-1';
    }

    protected function getEnvironmentSetUp($app)
    {
        $app->useEnvironmentPath(__DIR__.'/../..');
        $app->bootstrapWith([LoadEnvironmentVariables::class]);

        parent::getEnvironmentSetUp($app);
    }

    public function test_controller_returns_signed_urls()
    {
        Gate::define('uploadFiles', function ($user = null, $bucket = null) {
            return true;
        });

        $response = $this->withoutExceptionHandling()->json('POST', '/vapor/signed-storage-url?content_type=text/plain');

        $response->assertStatus(201);
        $this->assertIsString($response->original['uuid']);
        $this->assertIsString($response->original['key']);
        $this->assertIsString($response->original['url']);
        $this->assertIsArray($response->original['headers']);
        $this->assertSame('text/plain', $response->original['headers']['Content-Type']);

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
        Gate::define('uploadFiles', function ($user = null, $bucket = null) {
            return true;
        });

        $response = $this->withoutExceptionHandling()->json('POST', '/vapor/signed-storage-url?content_type=text/plain');
        $response->assertStatus(201);
        $this->assertStringContainsString('laravel-s3-test-1.custom-url', $response->original['url']);
    }

    public function test_cant_retrieve_signed_urls_without_proper_environment_variables()
    {
        Gate::define('uploadFiles', function ($user = null, $bucket = null) {
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
        Gate::define('uploadFiles', function ($user = null, $bucket = null) {
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
