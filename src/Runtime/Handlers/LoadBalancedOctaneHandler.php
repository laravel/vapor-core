<?php

namespace Laravel\Vapor\Runtime\Handlers;

use Laravel\Octane\RequestContext;
use Laravel\Vapor\Runtime\Http\LoadBalancedPsrRequestFactory;
use Laravel\Vapor\Runtime\LoadBalancedLambdaResponse;

class LoadBalancedOctaneHandler extends OctaneHandler
{
    /**
     * Convert Octane response to Lambda-ready response.
     *
     * @param  \Laravel\Octane\OctaneResponse  $octaneResponse
     * @return \Laravel\Vapor\Contracts\LambdaResponse
     */
    protected function response($octaneResponse)
    {
        return new LoadBalancedLambdaResponse(
            $octaneResponse->response->getStatusCode(),
            $octaneResponse->response->headers->all(),
            $octaneResponse->response->getContent()
        );
    }

    /**
     * Create a new Octane request from the incoming event.
     *
     * @param  array  $event
     * @return \Laravel\Octane\RequestContext
     */
    protected function request($event)
    {
        return new RequestContext([
            'psr7Request' => (new LoadBalancedPsrRequestFactory($event))->__invoke(),
        ]);
    }
}
