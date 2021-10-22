<?php

namespace Laravel\Vapor\Runtime;

use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Facade;
use Laravel\Vapor\Runtime\Http\Middleware\EnsureBinaryEncoding;
use Laravel\Vapor\Runtime\Http\Middleware\EnsureOnNakedDomain;
use Laravel\Vapor\Runtime\Http\Middleware\EnsureVanityUrlIsNotIndexed;
use Laravel\Vapor\Runtime\Http\Middleware\RedirectStaticAssets;

class HttpKernel
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * Create a new HTTP kernel instance.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Handle the incoming HTTP request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request)
    {
        $this->app->useStoragePath(StorageDirectories::PATH);

        if (static::shouldSendMaintenanceModeResponse($request)) {
            if (isset($_ENV['VAPOR_MAINTENANCE_MODE_SECRET']) &&
                $_ENV['VAPOR_MAINTENANCE_MODE_SECRET'] == $request->path()) {
                $response = static::bypassResponse($_ENV['VAPOR_MAINTENANCE_MODE_SECRET']);

                $this->app->terminate();
            } elseif (isset($_ENV['VAPOR_MAINTENANCE_MODE_SECRET']) &&
                static::hasValidBypassCookie($request, $_ENV['VAPOR_MAINTENANCE_MODE_SECRET'])) {
                $response = $this->sendRequest($request);
            } else {
                if ($request->wantsJson() && file_exists($_ENV['LAMBDA_TASK_ROOT'].'/503.json')) {
                    $response = JsonResponse::fromJsonString(
                        file_get_contents($_ENV['LAMBDA_TASK_ROOT'].'/503.json'), 503
                    );
                } else {
                    $response = new Response(
                        file_get_contents($_ENV['LAMBDA_TASK_ROOT'].'/503.html'), 503
                    );
                }

                $this->app->terminate();
            }
        } else {
            $response = $this->sendRequest($request);
        }

        return $response;
    }

    /**
     * Determine if a maintenance mode response should be sent.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public static function shouldSendMaintenanceModeResponse(Request $request)
    {
        return isset($_ENV['VAPOR_MAINTENANCE_MODE']) &&
            $_ENV['VAPOR_MAINTENANCE_MODE'] === 'true' &&
            'https://'.$request->getHttpHost() !== $_ENV['APP_VANITY_URL'];
    }

    /**
     * Determine if the incoming request has a maintenance mode bypass cookie.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $secret
     * @return bool
     */
    public static function hasValidBypassCookie($request, $secret)
    {
        return $request->cookie('laravel_maintenance') &&
            MaintenanceModeBypassCookie::isValid(
                $request->cookie('laravel_maintenance'),
                $secret
            );
    }

    /**
     * Redirect the user back to the root of the application with a maintenance mode bypass cookie.
     *
     * @param  string  $secret
     * @return \Illuminate\Http\RedirectResponse
     */
    public static function bypassResponse(string $secret)
    {
        $response = new RedirectResponse('/');

        $response->headers->setCookie(
            MaintenanceModeBypassCookie::create($secret)
        );

        return $response;
    }

    /**
     * Resolve the HTTP kernel for the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Http\Kernel
     */
    protected function resolveKernel(Request $request)
    {
        return tap($this->app->make(HttpKernelContract::class), function ($kernel) use ($request) {
            $this->app->instance('request', $request);

            Facade::clearResolvedInstance('request');

            $kernel->bootstrap();
        });
    }

    /**
     * Send the request to the kernel.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    protected function sendRequest(Request $request)
    {
        $kernel = $this->resolveKernel($request);

        $response = (new Pipeline)->send($request)
            ->through([
                new EnsureOnNakedDomain,
                new RedirectStaticAssets,
                new EnsureVanityUrlIsNotIndexed,
                new EnsureBinaryEncoding(),
            ])->then(function ($request) use ($kernel) {
                return $kernel->handle($request);
            });

        $kernel->terminate($request, $response);

        return $response;
    }
}
