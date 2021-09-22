<?php

namespace Laravel\Vapor\Runtime\Handlers;

use Laravel\Vapor\Runtime\LoadBalancedLambdaResponse;

class LoadBalancedFpmHandler extends FpmHandler
{
    /**
     * Covert FPM response to Lambda-ready response.
     *
     * @param  \Laravel\Vapor\Runtime\Fpm\FpmResponse  $fpmResponse
     * @return \Laravel\Vapor\Runtime\LoadBalancedLambdaResponse
     */
    public function response($fpmResponse)
    {
        return new LoadBalancedLambdaResponse(
            $fpmResponse->status,
            $fpmResponse->headers,
            $fpmResponse->body
        );
    }
}
