<?php

namespace Laravel\Vapor\Runtime\Handlers;

use Laravel\Octane\MarshalsPsr7RequestsAndResponses;
use Laravel\Octane\RequestContext;
use Laravel\Vapor\Contracts\LambdaEventHandler;
use Laravel\Vapor\Runtime\LambdaResponse;
use Laravel\Vapor\Runtime\Octane\Octane;
use Laravel\Vapor\Runtime\Request;
use Nyholm\Psr7\ServerRequest;

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
        $request = Request::fromLambdaEvent($event, $this->serverVariables());

        return new RequestContext([
            'psr7Request' => new ServerRequest(
                $request->serverVariables['REQUEST_METHOD'],
                $request->serverVariables['REQUEST_URI'],
                $request->headers,
                $request->body,
                $request->serverVariables['SERVER_PROTOCOL'],
                $request->serverVariables
            ),
        ]);
    }

    /**
     * Covert a response to Lambda-ready response.
     *
     * @param  \Laravel\Vapor\Runtime\Response  $response
     * @return \Laravel\Vapor\Runtime\LambdaResponse
     */
    protected function response($response)
    {
        return new LambdaResponse(
            $response->status,
            $response->headers,
            $response->body
        );
    }

    /**
     * Get the server variables.
     *
     * @return array
     */
    public function serverVariables()
    {
        return [
            'AWS_REQUEST_ID' => $_ENV['AWS_REQUEST_ID'] ?? null,
        ];
    }
}
