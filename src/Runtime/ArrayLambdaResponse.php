<?php

namespace Laravel\Vapor\Runtime;

use Laravel\Vapor\Contracts\LambdaResponse;

class ArrayLambdaResponse implements LambdaResponse
{
    /**
     * The response array.
     *
     * @var array
     */
    protected $response;

    /**
     * Create a new response instance.
     *
     * @param  array  $response
     * @return void
     */
    public function __construct(array $response)
    {
        $this->response = $response;
    }

    /**
     * Convert the response to API Gateway's supported format.
     *
     * @return array
     */
    public function toApiGatewayFormat()
    {
        return $this->response;
    }
}
