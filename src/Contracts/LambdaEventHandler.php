<?php

namespace Laravel\Vapor\Contracts;

interface LambdaEventHandler
{

    /**
     * Determines if the handler is responsible for the given event
     *
     * @param array $event
     * @return bool
     */
    public function supports(array $event);

    /**
     * Handle an incoming Lambda event.
     *
     * @param array $event
     * @param \Laravel\Vapor\Contracts\LambdaResponse
     */
    public function handle(array $event);
}
