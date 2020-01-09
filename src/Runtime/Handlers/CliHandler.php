<?php

namespace Laravel\Vapor\Runtime\Handlers;

use Throwable;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Laravel\Vapor\Runtime\ArrayLambdaResponse;
use Laravel\Vapor\Contracts\LambdaEventHandler;

class CliHandler implements LambdaEventHandler
{
    /**
     * Handle an incoming Lambda event.
     *
     * @param  array $event
     * @param  \Laravel\Vapor\Contracts\LambdaResponse
     * @return ArrayLambdaResponse
     */
    public function handle(array $event)
    {
        $output = [];

        $process = Process::fromShellCommandline(
            sprintf(
                "/opt/bin/php %s/artisan %s --no-interaction 2>&1",
                $_ENV['LAMBDA_TASK_ROOT'],
                trim($event['cli'])
            )
        )->setTimeout(null);

        $process->run(function ($type, $line) use (&$output) {
            if (! Str::containsAll($line, ['{"message":', '"level":'])) {
                $output[] = $line;
            }

            echo $line;
        });

        return new ArrayLambdaResponse(tap([
            'requestId' => $_ENV['AWS_REQUEST_ID'] ?? null,
            'logGroup' => $_ENV['AWS_LAMBDA_LOG_GROUP_NAME'] ?? null,
            'logStream' => $_ENV['AWS_LAMBDA_LOG_STREAM_NAME'] ?? null,
            'statusCode' => $process->getExitCode(),
            'output' => base64_encode(implode('', $output)),
        ], function ($response) use ($event) {
            $this->ping($event['callback'] ?? null, $response);
        }));
    }

    /**
     * Ping the given callback URL.
     *
     * @param  string  $callback
     * @param  array  $response
     * @return void
     */
    protected function ping($callback, $response)
    {
        if (! isset($callback)) {
            return;
        }

        try {
            (new Client)->post($callback, ['json' => $response]);
        } catch (Throwable $e) {
            //
        }
    }

    /**
     * Intercept incoming events to check if it should be handled by the CliHandler.
     *
     * @param array $event
     * @return array
     */
    public static function intercept(array $event)
    {
        if (! file_exists($_ENV['LAMBDA_TASK_ROOT'] . '/vapor/cli.php')) {
            return;
        }

        $commandCallbacks = require $_ENV['LAMBDA_TASK_ROOT'].'/vapor/cli.php';

        foreach ($commandCallbacks as $command => $callback) {
            if ($callback($event) === true) {
                if (Str::contains($command, '{payload}')) {
                    $payload = base64_encode(json_encode($event));
                    $command = str_replace('{payload}', $payload, $command);
                }

                $event['cli'] = $command;
                
                break;
            }
        }

        return $event;
    }
}
