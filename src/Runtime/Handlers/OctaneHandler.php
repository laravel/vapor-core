<?php

namespace Laravel\Vapor\Runtime\Handlers;

use Laravel\Octane\MarshalsPsr7RequestsAndResponses;
use Laravel\Octane\RequestContext;
use Laravel\Vapor\Contracts\LambdaEventHandler;
use Laravel\Vapor\Runtime\Http\PsrRequestFactory;
use Laravel\Vapor\Runtime\LambdaResponse;
use Laravel\Vapor\Runtime\Octane\Octane;

class OctaneHandler implements LambdaEventHandler
{
    use MarshalsPsr7RequestsAndResponses;

    /**
     * Handle an incoming Lambda event.
     *
     * @param  array  $event
     * @return \Laravel\Vapor\Contracts\LambdaResponse
     */
    public function handle(array $event)
    {
        $request = $this->request($event);

        return $this->response(
            Octane::handle($request)
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
            'psr7Request' => (new PsrRequestFactory($event))->__invoke(),
        ]);
    }

    /**
     * Convert Octane response to Lambda-ready response.
     *
     * @param  \Laravel\Octane\OctaneResponse  $octaneResponse
     * @return \Laravel\Vapor\Contracts\LambdaResponse
     */
    protected function response($octaneResponse)
    {
        return new LambdaResponse(
            $octaneResponse->response->getStatusCode(),
            $octaneResponse->response->headers->all(),
            $octaneResponse->response->getContent()
        );
    }
}
