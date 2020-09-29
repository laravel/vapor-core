<?php

namespace Laravel\Vapor\Runtime;

use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Facade;
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
            $response = new Response(
                file_get_contents($_ENV['LAMBDA_TASK_ROOT'].'/503.html'), 503
            );

            $this->app->terminate();
        } else {
            $kernel = $this->resolveKernel($request);

            $response = (new Pipeline)->send($request)
                ->through([
                    new EnsureOnNakedDomain,
                    new RedirectStaticAssets,
                    new EnsureVanityUrlIsNotIndexed,
                ])->then(function ($request) use ($kernel) {
                    return $kernel->handle($request);
                });

            $kernel->terminate($request, $response);
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
}
