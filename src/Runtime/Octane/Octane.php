<?php

namespace Laravel\Vapor\Runtime\Octane;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\MySqlConnection;
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
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class Octane implements Client
{
    use MarshalsPsr7RequestsAndResponses;

    /**
     * The default database session time-to-live.
     */
    const DB_SESSION_DEFAULT_TTL = 28800;

    /**
     * List of Octane database sessions.
     *
     * @var bool
     */
    protected static $databaseSessions = [
        // ..
    ];

    /**
     * The Octane response, if any.
     *
     * @var \Laravel\Octane\OctaneResponse|null
     */
    protected static $response;

    /**
     * The Octane worker, if any.
     *
     * @var \Laravel\Octane\Worker|null
     */
    protected static $worker;

    /**
     * Boots an Octane worker instance.
     *
     * @param  string  $basePath
     * @param  bool  $databaseSessionPersist
     * @param  int  $databaseSessionTtl
     * @return void
     */
    public static function boot($basePath, $databaseSessionPersist = false, $databaseSessionTtl = 0)
    {
        self::ensureServerSoftware('vapor');

        $databaseSessionTtl = (int) $databaseSessionTtl;

        static::$worker = tap(new Worker(
                new ApplicationFactory($basePath), new self)
        )->boot()->onRequestHandled(static::manageDatabaseSessions($databaseSessionPersist, $databaseSessionTtl));

        if ($databaseSessionPersist && $databaseSessionTtl > 0) {
            static::worker()->application()->make('db')->beforeExecuting(function ($query, $bindings, $connection) {
                if ($connection instanceof MySqlConnection && ! in_array($connection->getName(), static::$databaseSessions)) {
                    static::$databaseSessions[] = $connection->getName();

                    $connection->unprepared(sprintf(
                        'SET SESSION wait_timeout=%s', static::DB_SESSION_DEFAULT_TTL
                    ));
                }
            });
        }
    }

    /**
     * Manage the database sessions.
     *
     * @param  bool  $databaseSessionPersist
     * @param  int  $databaseSessionTtl
     * @return callable
     */
    protected static function manageDatabaseSessions($databaseSessionPersist, $databaseSessionTtl)
    {
        return function ($request, $response, $sandbox) use ($databaseSessionPersist, $databaseSessionTtl) {
            if (! $sandbox->resolved('db') || ($databaseSessionPersist && $databaseSessionTtl == 0)) {
                return;
            }

            $connections = collect($sandbox->make('db')->getConnections());

            if (! $databaseSessionPersist) {
                return $connections->each->disconnect();
            }

            $connections->filter(function ($connection) {
                $hasSession = in_array($connection->getName(), static::$databaseSessions);

                if (! $hasSession) {
                    $connection->disconnect();
                }

                return $hasSession;
            })->map->getRawPdo()->filter(function ($pdo) {
                return $pdo instanceof PDO;
            })->each->exec(sprintf(
                'SET SESSION wait_timeout=%s', $databaseSessionTtl
            ));
        };
    }

    /**
     * Handle the given Octane request.
     *
     * @param  \Laravel\Octane\RequestContext  $request
     * @return \Laravel\Vapor\Runtime\Response
     */
    public static function handle($request)
    {
        [$request, $context] = (new self)->marshalRequest($request);

        static::$databaseSessions = [];

        self::worker()->application()->useStoragePath(StorageDirectories::PATH);

        if (HttpKernel::shouldSendMaintenanceModeResponse($request)) {
            if (isset($_ENV['VAPOR_MAINTENANCE_MODE_SECRET']) &&
                $_ENV['VAPOR_MAINTENANCE_MODE_SECRET'] == $request->path()) {
                $response = HttpKernel::bypassResponse($_ENV['VAPOR_MAINTENANCE_MODE_SECRET']);
            } elseif (isset($_ENV['VAPOR_MAINTENANCE_MODE_SECRET']) &&
                static::hasValidBypassCookie($request, $_ENV['VAPOR_MAINTENANCE_MODE_SECRET'])) {
                $response = static::sendRequest($request, $context);
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
            $response = static::sendRequest($request, $context);
        }

        $content = $response instanceof BinaryFileResponse
            ? $response->getFile()->getContent()
            : $response->getContent();

        return tap(new Response(
            $content,
            $response->headers->all(),
            $response->getStatusCode()
        ), static function () {
            static::$response = null;
        });
    }

    /**
     * Send the request to the worker.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Laravel\Octane\RequestContext  $context
     * @return \Laravel\Octane\OctaneResponse
     */
    protected static function sendRequest($request, $context)
    {
        return (new Pipeline)->send($request)
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

    /**
     * Determine if the incoming request has a maintenance mode bypass cookie.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $secret
     * @return bool
     */
    public static function hasValidBypassCookie($request, $secret)
    {
        return HttpKernel::hasValidBypassCookie($request, $secret);
    }

    /**
     * Terminates an Octane worker instance, if any.
     *
     * @return void
     */
    public static function terminate()
    {
        if (static::$worker) {
            static::$worker->terminate();

            static::$worker = null;

            self::ensureServerSoftware(null);
        }
    }

    /**
     * Marshal the given Octane request context into an Laravel foundation request.
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
     * Stores the response in the instance.
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
            static::$response = new OctaneResponse(
                $app[ExceptionHandler::class]->render($request, $e)
            );
        } catch (Throwable $throwable) {
            fwrite(STDERR, $throwable->getMessage());
            fwrite(STDERR, $e->getMessage());

            static::$response = new OctaneResponse(
                new \Illuminate\Http\Response('', 500)
            );
        }
    }

    /**
     * Ensures the given software name is set globally.
     *
     * @param  string|null  $software
     * @return void
     */
    protected static function ensureServerSoftware($software)
    {
        $_ENV['SERVER_SOFTWARE'] = $software;
        $_SERVER['SERVER_SOFTWARE'] = $software;
    }

    /**
     * Get the Octane worker, if any.
     *
     * @return \Laravel\Octane\Worker|null
     */
    public static function worker()
    {
        return static::$worker;
    }
}
