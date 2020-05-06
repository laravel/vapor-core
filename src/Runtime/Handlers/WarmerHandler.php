<?php

namespace Laravel\Vapor\Runtime\Handlers;

use AsyncAws\Core\Result;
use AsyncAws\Lambda\LambdaClient;
use Laravel\Vapor\Contracts\LambdaEventHandler;
use Laravel\Vapor\Runtime\ArrayLambdaResponse;
use Laravel\Vapor\Runtime\Logger;
use Symfony\Component\HttpClient\HttpClient;
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

            $promises = $this->buildPromises($this->lambdaClient(), $event);
            foreach (Result::wait($promises) as $result) {};
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
     * @param  \AsyncAws\Lambda\LambdaClient  $lambda
     * @param  array  $event
     * @return array
     */
    protected function buildPromises(LambdaClient $lambda, array $event)
    {
        return collect(range(1, $event['concurrency'] - 1))
                ->mapWithKeys(function ($i) use ($lambda, $event) {
                    return ['warmer-'.$i => $lambda->invoke([
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
     * @return \AsyncAws\Lambda\LambdaClient
     */
    protected function lambdaClient()
    {
        return new LambdaClient(
            ['region' => $_ENV['AWS_DEFAULT_REGION']],
            null,
            HttpClient::create(['timeout'=>5])
        );
    }
}
