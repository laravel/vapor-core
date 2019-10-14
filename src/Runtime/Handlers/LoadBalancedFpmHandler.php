<?php

namespace Laravel\Vapor\Runtime\Handlers;

use Laravel\Vapor\Runtime\Fpm\LoadBalancedFpmLambdaResponse;

class LoadBalancedFpmHandler extends FpmHandler
{

    public function supports(array $event)
    {
        return isset($event['requestContext']['elb']);
    }

    /**
     * Covert FPM response to Lambda-ready response.
     *
     * @param  \Laravel\Vapor\Runtime\Fpm\FpmResponse  $fpmResponse
     * @return \Laravel\Vapor\Runtime\Fpm\FpmLambdaResponse
     */
    public function response($fpmResponse)
    {
        return new LoadBalancedFpmLambdaResponse(
            $fpmResponse->status,
            $fpmResponse->headers,
            $fpmResponse->body
        );
    }
}
