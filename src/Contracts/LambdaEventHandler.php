<?php

namespace Laravel\Vapor\Contracts;

interface LambdaEventHandler
{
    /**
     * Handle an incoming Lambda event.
     *
     * @param  array  $event
     * @return \Laravel\Vapor\Contracts\LambdaResponse
     */
    public function handle(array $event);
}
