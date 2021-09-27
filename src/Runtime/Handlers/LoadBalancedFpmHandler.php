<?php

namespace Laravel\Vapor\Runtime\Handlers;

use Laravel\Vapor\Runtime\LoadBalancedLambdaResponse;

class LoadBalancedFpmHandler extends FpmHandler
{
    /**
     * Covert a response to Lambda-ready response.
     *
     * @param  \Laravel\Vapor\Runtime\Response  $response
     * @return \Laravel\Vapor\Runtime\LoadBalancedLambdaResponse
     */
    public function response($response)
    {
        return new LoadBalancedLambdaResponse(
            $response->status,
            $response->headers,
            $response->body
        );
    }
}
