<?php

namespace Laravel\Vapor\Runtime\Handlers;

use Laravel\Vapor\Contracts\LambdaEventHandler;
use Laravel\Vapor\Runtime\PayloadFormatVersion2LambdaResponse;

class PayloadFormatVersion2FpmHandler extends FpmHandler implements LambdaEventHandler
{
    /**
     * Covert a response to Lambda-ready response.
     *
     * @param  \Laravel\Vapor\Runtime\Response  $response
     * @return \Laravel\Vapor\Runtime\LambdaResponse
     */
    public function response($response)
    {
        return new PayloadFormatVersion2LambdaResponse(
            $response->status,
            $response->headers,
            $response->body
        );
    }
}
