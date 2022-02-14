<?php

namespace Laravel\Vapor\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Laravel\Vapor\Runtime\Http\Middleware\RedirectStaticAssets;
use Laravel\Vapor\VaporServiceProvider;
use Orchestra\Testbench\TestCase;

class RedirectStaticAssetsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['ASSET_URL'] = 'https://asset-url.com';

        Route::get('/favicon.ico', function () {
            return 'My own favicon.';
        })->middleware(RedirectStaticAssets::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            VaporServiceProvider::class,
        ];
    }

    public function test_redirects_to_favicon_ico()
    {
        $response = $this->get('/favicon.ico');
        $response->assertStatus(302)->assertRedirect('https://asset-url.com/favicon.ico');

        config()->set('vapor.redirect_favicon_ico', false);

        $response = $this->get('/favicon.ico');
        $response->assertStatus(200)->assertSee('My own favicon.');
    }
}
