<?php

namespace Laravel\Vapor\Runtime\Octane;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\Contracts\Client;
use Laravel\Octane\MarshalsPsr7RequestsAndResponses;
use Laravel\Octane\OctaneResponse;
use Laravel\Octane\RequestContext;
use Laravel\Octane\Worker;
use Laravel\Vapor\Runtime\Http\Middleware\EnsureBinaryEncoding;
use Laravel\Vapor\Runtime\Http\Middleware\EnsureOnNakedDomain;
use Laravel\Vapor\Runtime\Http\Middleware\EnsureVanityUrlIsNotIndexed;
use Laravel\Vapor\Runtime\Http\Middleware\RedirectStaticAssets;
use Laravel\Vapor\Runtime\HttpKernel;
use Laravel\Vapor\Runtime\Response;
use Laravel\Vapor\Runtime\StorageDirectories;
use PDO;
use Throwable;

class Octane implements Client
{
    use MarshalsPsr7RequestsAndResponses;

    /**
     * Default db session duration.
     */
    const DB_SESSION_DEFAULT_TTL = 28800;

    /**
     * If the current request holds a db session.
     *
     * @var bool
     */
    protected static $dbSession = false;

    /**
     * The octane worker.
     *
     * @var \Laravel\Octane\OctaneResponse|null
     */
    protected static $response;

    /**
     * The octane worker.
     *
     * @var \Laravel\Octane\Worker|null
     */
    protected static $worker;

    /**
     * Boots an octane worker instance.
     *
     * @param  string  $basePath
     * @param  int  $dbSessionTtl
     * @return void
     */
    public static function boot($basePath, $dbSessionTtl = 0)
    {
        static::$worker = tap(new Worker(
                new ApplicationFactory($basePath), new self)
        )->boot()->onRequestHandled(static::ensureDbSessionTtl($dbSessionTtl));

        if ($dbSessionTtl) {
            static::worker()->application()->make('db')->beforeExecuting(function ($query, $bindings, $connection) {
                if (static::$dbSession == false) {
                    static::$dbSession = true;

                    $connection->unprepared(sprintf(
                        'SET SESSION wait_timeout=%s', static::DB_SESSION_DEFAULT_TTL
                    ));
                }
            });
        }
    }

    /**
     * @param  int  $dbSessionTtl
     * @return callable
     */
    protected static function ensureDbSessionTtl($dbSessionTtl)
    {
        return function ($request, $response, $sandbox) use ($dbSessionTtl) {
            if (! static::$dbSession) {
                return;
            }

            $connections = collect($sandbox->make('db')->getConnections());

            if ($dbSessionTtl == 0) {
                return $connections->each->disconnect();
            }

            $connections->map->getRawPdo()->filter(function ($pdo) {
                return $pdo instanceof PDO;
            })->each->exec(sprintf(
                'SET SESSION wait_timeout=%s', $dbSessionTtl
            ));
        };
    }

    /**
     * @param  \Laravel\Octane\RequestContext  $request
     * @return \Laravel\Vapor\Runtime\Response
     */
    public static function handle($request)
    {
        [$request, $context] = (new self)->marshalRequest($request);

        static::$dbSession = false;

        self::worker()->application()->useStoragePath(StorageDirectories::PATH);

        if (HttpKernel::shouldSendMaintenanceModeResponse($request)) {
            if (isset($_ENV['VAPOR_MAINTENANCE_MODE_SECRET']) &&
                $_ENV['VAPOR_MAINTENANCE_MODE_SECRET'] == $request->path()) {
                $response = HttpKernel::bypassResponse($_ENV['VAPOR_MAINTENANCE_MODE_SECRET']);
            } elseif (isset($_ENV['VAPOR_MAINTENANCE_MODE_SECRET']) &&
                HttpKernel::hasValidBypassCookie($request, $_ENV['VAPOR_MAINTENANCE_MODE_SECRET'])) {
            } elseif ($request->wantsJson() && file_exists($_ENV['LAMBDA_TASK_ROOT'].'/503.json')) {
                $response = JsonResponse::fromJsonString(
                    file_get_contents($_ENV['LAMBDA_TASK_ROOT'].'/503.json'), 503
                );
            } else {
                $response = new \Illuminate\Http\Response(
                    file_get_contents($_ENV['LAMBDA_TASK_ROOT'].'/503.html'), 503
                );
            }
        } else {
            $response = (new Pipeline)->send($request)
                ->through([
                    new EnsureOnNakedDomain,
                    new RedirectStaticAssets,
                    new EnsureVanityUrlIsNotIndexed,
                    new EnsureBinaryEncoding(),
                ])->then(function ($request) use ($context) {
                    static::$worker->handle($request, $context);

                    return static::$response->response;
                });
        }

        return tap(new Response(
            $response->getContent(),
            $response->headers->all(),
            $response->getStatusCode()
        ), static function () {
            static::$response = null;
        });
    }

    /**
     * Terminates an octane worker instance, if any.
     *
     * @return void
     */
    public static function terminate()
    {
        if (static::$worker) {
            static::$worker->terminate();

            static::$worker = null;
        }
    }

    /**
     * Gets the octane worker, if any.
     *
     * @return \Laravel\Octane\Worker|null
     */
    public static function worker()
    {
        return static::$worker;
    }

    /**
     * Marshal the given request context into an Illuminate request.
     *
     * @param  \Laravel\Octane\RequestContext  $context
     * @return array
     */
    public function marshalRequest(RequestContext $context): array
    {
        return [
            static::toHttpFoundationRequest($context->psr7Request),
            $context,
        ];
    }

    /**
     * Send the response to the server.
     *
     * @param  \Laravel\Octane\RequestContext  $context
     * @param  \Laravel\Octane\OctaneResponse  $response
     * @return void
     */
    public function respond(RequestContext $context, OctaneResponse $response): void
    {
        static::$response = $response;
    }

    /**
     * Send an error message to the server.
     *
     * @param  \Throwable  $e
     * @param  \Illuminate\Foundation\Application  $app
     * @param  \Illuminate\Http\Request  $request
     * @param  \Laravel\Octane\RequestContext  $context
     * @return void
     */
    public function error(Throwable $e, Application $app, Request $request, RequestContext $context): void
    {
        try {
            $app[ExceptionHandler::class]->report($e);
        } catch (Throwable $throwable) {
            fwrite(STDERR, $throwable->getMessage());
            fwrite(STDERR, $e->getMessage());
        }
    }
}
