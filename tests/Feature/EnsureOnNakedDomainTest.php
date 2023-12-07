<?php

namespace Laravel\Vapor\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Laravel\Vapor\Runtime\Http\Middleware\EnsureOnNakedDomain;
use Laravel\Vapor\VaporServiceProvider;
use Orchestra\Testbench\TestCase;

class EnsureOnNakedDomainTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['APP_VANITY_URL'] = 'https://something.vapor-farm.com';

        Route::get('/', function () {
            return 'Hello World';
        })->middleware(EnsureOnNakedDomain::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            VaporServiceProvider::class,
        ];
    }

    /**
     * @dataProvider useCases
     */
    public function test_redirects($useCase)
    {
        config()->set('vapor.redirect_to_root', $useCase['redirect_to_root']);
        config()->set('app.url', $useCase['app_url']);

        $response = $this->get($useCase['request_url']);

        $response->assertStatus($useCase['response_status']);

        if ($useCase['response_status'] == 301) {
            $response->assertRedirect($useCase['redirected_to']);
        }
    }

    public static function useCases()
    {
        return [
            [[
                'app_url' => 'https://domain.com',
                'request_url' => 'https://domain.com',
                'redirect_to_root' => true,
                'response_status' => 200,
            ]],
            [[
                'app_url' => 'https://domain.net.io',
                'request_url' => 'https://domain.net.io',
                'redirect_to_root' => true,
                'response_status' => 200,
            ]],
            [[
                'app_url' => 'https://domain.com',
                'request_url' => 'https://www.domain.com',
                'redirect_to_root' => true,
                'response_status' => 301,
                'redirected_to' => 'https://domain.com',
            ]],
            [[
                'app_url' => 'https://domain.net.io',
                'request_url' => 'https://www.domain.net.io',
                'redirect_to_root' => true,
                'response_status' => 301,
                'redirected_to' => 'https://domain.net.io',
            ]],
            [[
                'app_url' => 'https://domain.com',
                'request_url' => 'https://sub.domain.com',
                'redirect_to_root' => true,
                'response_status' => 200,
            ]],
            [[
                'app_url' => 'https://domain.net.io',
                'request_url' => 'https://sub.domain.net.io',
                'redirect_to_root' => true,
                'response_status' => 200,
            ]],

            // redirect_to_root => false
            [[
                'app_url' => 'https://domain.com',
                'request_url' => 'https://www.domain.com',
                'redirect_to_root' => false,
                'response_status' => 200,
            ]],
            [[
                'app_url' => 'https://domain.net.io',
                'request_url' => 'https://www.domain.net.io',
                'redirect_to_root' => false,
                'response_status' => 200,
            ]],
            [[
                'app_url' => 'https://domain.com',
                'request_url' => 'https://domain.com',
                'redirect_to_root' => false,
                'response_status' => 301,
                'redirected_to' => 'https://www.domain.com',
            ]],
            [[
                'app_url' => 'https://domain.net.io',
                'request_url' => 'https://domain.net.io',
                'redirect_to_root' => false,
                'response_status' => 301,
                'redirected_to' => 'https://www.domain.net.io',
            ]],
            [[
                'app_url' => 'https://domain.com',
                'request_url' => 'https://sub.domain.com',
                'redirect_to_root' => false,
                'response_status' => 200,
            ]],
            [[
                'app_url' => 'https://domain.net.io',
                'request_url' => 'https://sub.domain.net.io',
                'redirect_to_root' => false,
                'response_status' => 200,
            ]],
        ];
    }
}
