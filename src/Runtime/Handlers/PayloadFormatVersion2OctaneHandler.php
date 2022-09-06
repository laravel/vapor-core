<?php

namespace Laravel\Vapor\Runtime\Handlers;

use Laravel\Vapor\Contracts\LambdaEventHandler;
use Laravel\Vapor\Runtime\PayloadFormatVersion2LambdaResponse;

class PayloadFormatVersion2OctaneHandler extends OctaneHandler implements LambdaEventHandler
{
    /**
     * Covert a response to Lambda-ready response.
     *
     * @param  \Laravel\Vapor\Runtime\Response  $response
     * @return \Laravel\Vapor\Runtime\LambdaResponse
     */
    protected function response($response)
    {
        return new PayloadFormatVersion2LambdaResponse(
            $response->status,
            $response->headers,
            $response->body
        );
    }
}
