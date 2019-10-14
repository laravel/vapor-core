<?php

namespace Laravel\Vapor\Runtime\Handlers;

use Laravel\Vapor\Runtime\ArrayLambdaResponse;
use Laravel\Vapor\Contracts\LambdaEventHandler;

class WarmerPingHandler implements LambdaEventHandler
{

    public function supports(array $event)
    {
        return isset($event['vaporWarmerPing']);
    }

    /**
     * Handle an incoming Lambda event.
     *
     * @param  array  $event
     * @param  \Laravel\Vapor\Contracts\LambdaResponse
     */
    public function handle(array $event)
    {
        usleep(50 * 1000);

        return new ArrayLambdaResponse([
            'output' => 'Warmer ping handled.'
        ]);
    }
}
