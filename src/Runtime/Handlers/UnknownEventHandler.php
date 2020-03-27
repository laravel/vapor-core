<?php

namespace Laravel\Vapor\Runtime\Handlers;

use Laravel\Vapor\Contracts\LambdaEventHandler;
use Laravel\Vapor\Runtime\ArrayLambdaResponse;
use Laravel\Vapor\Runtime\Logger;

class UnknownEventHandler implements LambdaEventHandler
{
    /**
     * Handle an incoming Lambda event.
     *
     * @param  array  $event
     * @param  \Laravel\Vapor\Contracts\LambdaResponse
     */
    public function handle(array $event)
    {
        Logger::info('Unknown event type received by application.', [
            'event' => $event
        ]);

        return new ArrayLambdaResponse([
            'output' => 'Unknown event type.'
        ]);
    }
}
