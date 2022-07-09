<?php

namespace Laravel\Vapor\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Laravel\Vapor\VaporServiceProvider;
use Orchestra\Testbench\TestCase;

class SignedStorageUrlEndpointTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            VaporServiceProvider::class,
        ];
    }

    public function test_signed_url_preserves_its_components(): void
    {
        Config::set([
            'filesystems.disks.s3.bucket' => $_ENV['AWS_BUCKET'] = 'storage',
            'filesystems.disks.s3.key' => $_ENV['AWS_ACCESS_KEY_ID'] = 'sail',
            'filesystems.disks.s3.region' => $_ENV['AWS_DEFAULT_REGION'] = 'us-east-1',
            'filesystems.disks.s3.secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] = 'password',
            'filesystems.disks.s3.url' => $_ENV['AWS_URL'] = 'http://minio:9000',
            'filesystems.disks.s3.use_path_style_endpoint' => true,
        ]);

        Gate::define('uploadFiles', static function ($user = null, $bucket = null): bool {
            return true;
        });

        $response = $this->json('POST', '/vapor/signed-storage-url')
            ->assertStatus(201);

        $components = (object) [
            'actual' => parse_url($response->json('url')),
            'expected' => parse_url($_ENV['AWS_URL'].'/'.$_ENV['AWS_BUCKET']),
        ];

        $this->assertEquals($components->expected['scheme'], $components->actual['scheme']);
        $this->assertEquals($components->expected['host'], $components->actual['host']);
        $this->assertEquals($components->expected['port'], $components->actual['port']);
        $this->assertStringStartsWith($components->expected['path'], $components->actual['path']);
    }

    public function test_signed_url_expires_after(): void
    {
        Config::set([
            'filesystems.disks.s3.bucket' => $_ENV['AWS_BUCKET'] = 'storage',
            'filesystems.disks.s3.key' => $_ENV['AWS_ACCESS_KEY_ID'] = 'sail',
            'filesystems.disks.s3.region' => $_ENV['AWS_DEFAULT_REGION'] = 'us-east-1',
            'filesystems.disks.s3.secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] = 'password',
            'filesystems.disks.s3.url' => $_ENV['AWS_URL'] = 'http://minio:9000',
            'filesystems.disks.s3.use_path_style_endpoint' => true,
        ]);

        Gate::define('uploadFiles', static function ($user = null, $bucket = null): bool {
            return true;
        });

        $response = $this->json('POST', '/vapor/signed-storage-url');
        parse_str($response->json()['url'], $queryParams);
        $this->assertEquals(300, $queryParams['X-Amz-Expires']);

        config()->set('vapor.signed_storage_url_expires_after', 6);
        $response = $this->json('POST', '/vapor/signed-storage-url');
        parse_str($response->json()['url'], $queryParams);
        $this->assertEquals(360, $queryParams['X-Amz-Expires']);
    }

    public function test_custom_key_prefix(): void
    {
        Config::set([
            'filesystems.disks.s3.bucket' => $_ENV['AWS_BUCKET'] = 'storage',
            'filesystems.disks.s3.key' => $_ENV['AWS_ACCESS_KEY_ID'] = 'sail',
            'filesystems.disks.s3.region' => $_ENV['AWS_DEFAULT_REGION'] = 'us-east-1',
            'filesystems.disks.s3.secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] = 'password',
            'filesystems.disks.s3.url' => $_ENV['AWS_URL'] = 'http://minio:9000',
            'filesystems.disks.s3.use_path_style_endpoint' => true,
        ]);

        Gate::define('uploadFiles', static function ($user = null, $bucket = null): bool {
            return true;
        });

        $response = $this->json('POST', '/vapor/signed-storage-url');
        parse_str($response->json()['url'], $queryParams);
        $this->assertEquals(300, $queryParams['X-Amz-Expires']);

        config()->set('vapor.key_prefix', 'testKeyPrefix/');
        $response = $this->json('POST', '/vapor/signed-storage-url');
        $this->assertEquals('testKeyPrefix/'.$response->json('uuid'), $response->json('key'));
    }

    public function test_default_key_prefix(): void
    {
        Config::set([
            'filesystems.disks.s3.bucket' => $_ENV['AWS_BUCKET'] = 'storage',
            'filesystems.disks.s3.key' => $_ENV['AWS_ACCESS_KEY_ID'] = 'sail',
            'filesystems.disks.s3.region' => $_ENV['AWS_DEFAULT_REGION'] = 'us-east-1',
            'filesystems.disks.s3.secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] = 'password',
            'filesystems.disks.s3.url' => $_ENV['AWS_URL'] = 'http://minio:9000',
            'filesystems.disks.s3.use_path_style_endpoint' => true,
        ]);

        Gate::define('uploadFiles', static function ($user = null, $bucket = null): bool {
            return true;
        });

        $response = $this->json('POST', '/vapor/signed-storage-url');
        parse_str($response->json()['url'], $queryParams);
        $this->assertEquals(300, $queryParams['X-Amz-Expires']);

        $response = $this->json('POST', '/vapor/signed-storage-url');
        $this->assertEquals('tmp/'.$response->json('uuid'), $response->json('key'));
    }
}
