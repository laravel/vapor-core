<?php

namespace Laravel\Vapor\Runtime\Handlers;

use Aws\Lambda\LambdaClient;
use GuzzleHttp\Promise;
use Laravel\Vapor\Contracts\LambdaEventHandler;
use Laravel\Vapor\Runtime\ArrayLambdaResponse;
use Laravel\Vapor\Runtime\Logger;
use Throwable;

class WarmerHandler implements LambdaEventHandler
{
    /**
     * Handle an incoming Lambda event.
     *
     * @param  array  $event
     * @param  \Laravel\Vapor\Contracts\LambdaResponse
     */
    public function handle(array $event)
    {
        try {
            Logger::info('Executing warming requests...');

            Promise\settle(
                $this->buildPromises($this->lambdaClient(), $event)
            )->wait();
        } catch (Throwable $e) {
            Logger::error($e->getMessage(), ['exception' => $e]);
        }

        return new ArrayLambdaResponse([
            'output' => 'Warmer event handled.'
        ]);
    }

    /**
     * Build the array of warmer invocation promises.
     *
     * @param  \Aws\Lambda\LambdaClient  $lambda
     * @param  array  $event
     * @return array
     */
    protected function buildPromises(LambdaClient $lambda, array $event)
    {
        return collect(range(1, $event['concurrency'] - 1))
                ->mapWithKeys(function ($i) use ($lambda, $event) {
                    return ['warmer-'.$i => $lambda->invokeAsync([
                        'FunctionName' => $event['functionName'],
                        'Qualifier' => $event['functionAlias'],
                        'LogType' => 'None',
                        'Payload' => json_encode(['vaporWarmerPing' => true]),
                    ])];
                })->all();
    }

    /**
     * Get the Lambda client instance.
     *
     * @return \Aws\Lambda\LambdaClient
     */
    protected function lambdaClient()
    {
        return new LambdaClient([
            'region' => $_ENV['AWS_DEFAULT_REGION'],
            'version' => 'latest',
            'http' => [
                'timeout' => 5,
                'connect_timeout' => 5,
            ],
        ]);
    }
}
