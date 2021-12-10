<?php

namespace Laravel\Vapor\Runtime\Handlers;

use Illuminate\Contracts\Console\Kernel;
use Laravel\Vapor\Contracts\LambdaEventHandler;
use Laravel\Vapor\Events\LambdaEvent;
use Laravel\Vapor\Runtime\ArrayLambdaResponse;
use Laravel\Vapor\Runtime\StorageDirectories;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

class QueueHandler implements LambdaEventHandler
{
    /**
     * The cached application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    public static $app;

    /**
     * Create a new Queue handler instance.
     *
     * @return void
     */
    public function __construct()
    {
        if (! isset(static::$app)) {
            static::$app = require $_ENV['LAMBDA_TASK_ROOT'].'/bootstrap/app.php';
        }
    }

    /**
     * Handle an incoming Lambda event.
     *
     * @param  array  $event
     * @return ArrayLambdaResponse
     */
    public function handle(array $event)
    {
        $commandOptions = trim(sprintf(
            '--delay=%s --timeout=%s --tries=%s %s',
            $_ENV['SQS_DELAY'] ?? 3,
            $_ENV['QUEUE_TIMEOUT'] ?? 0,
            $_ENV['SQS_TRIES'] ?? 3,
            ($_ENV['SQS_FORCE'] ?? false) ? '--force' : ''
        ));

        try {
            static::$app->useStoragePath(StorageDirectories::PATH);

            $consoleKernel = static::$app->make(Kernel::class);

            static::$app->bind(LambdaEvent::class, function () use ($event) {
                return new LambdaEvent($event);
            });

            $consoleInput = new StringInput(
                'vapor:work '.$commandOptions.' --no-interaction'
            );

            $status = $consoleKernel->handle(
                $consoleInput, $output = new BufferedOutput
            );

            $consoleKernel->terminate($consoleInput, $status);

            return new ArrayLambdaResponse([
                'requestId' => $_ENV['AWS_REQUEST_ID'] ?? null,
                'logGroup' => $_ENV['AWS_LAMBDA_LOG_GROUP_NAME'] ?? null,
                'logStream' => $_ENV['AWS_LAMBDA_LOG_STREAM_NAME'] ?? null,
                'statusCode' => $status,
                'output' => base64_encode($output->fetch()),
            ]);
        } finally {
            unset(static::$app[LambdaEvent::class]);

            $this->terminate();
        }
    }

    /**
     * Terminate any relevant application services.
     *
     * @return void
     */
    protected function terminate()
    {
        if (static::$app->resolved('db') && ($_ENV['VAPOR_QUEUE_DATABASE_SESSION_PERSIST'] ?? false) !== 'true') {
            collect(static::$app->make('db')->getConnections())->each->disconnect();
        }
    }
}
