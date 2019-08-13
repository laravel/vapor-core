<?php

namespace Laravel\Vapor\Contracts;

interface LambdaResponse
{
    /**
     * Convert the response to API Gateway's supported format.
     *
     * @return array
     */
    public function toApiGatewayFormat();
}
