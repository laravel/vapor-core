<?php

namespace Laravel\Vapor\Runtime\Handlers;

use Laravel\Vapor\Runtime\Http\LoadBalancedPsrRequestFactory;
use Laravel\Vapor\Runtime\LoadBalancedPsrLambdaResponseFactory;
use Psr\Http\Message\ResponseInterface;

class LoadBalancedAppHandler extends AppHandler
{
    /**
     * Create a new PSR-7 compliant request from the incoming event.
     *
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    protected function marshalRequest(array $event)
    {
        return (new LoadBalancedPsrRequestFactory($event))->__invoke();
    }

    /**
     * Marshal the PSR-7 response to a Lambda response.
     *
     * @param  \Psr\Http\Message\ResponseInterface  $response
     * @return \Laravel\Vapor\Runtime\ArrayLambdaResponse
     */
    protected function marshalResponse(ResponseInterface $response)
    {
        return LoadBalancedPsrLambdaResponseFactory::fromPsrResponse($response);
    }
}
