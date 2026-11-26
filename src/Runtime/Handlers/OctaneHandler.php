<?php

namespace Laravel\Vapor\Runtime\Handlers;

use Laravel\Octane\MarshalsPsr7RequestsAndResponses;
use Laravel\Vapor\Contracts\LambdaEventHandler;
use Laravel\Vapor\Runtime\LambdaResponse;
use Laravel\Vapor\Runtime\Octane\Octane;
use Laravel\Vapor\Runtime\Octane\OctaneRequestContextFactory;
use Laravel\Vapor\Runtime\Response;
use Symfony\Component\HttpFoundation\Exception\RequestExceptionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
        try {
            $request = $this->request($event);

            return $this->response(
                Octane::handle($request)
            );
        } catch (RequestExceptionInterface|BadRequestHttpException $e) {
            return $this->response(new Response(
                json_encode(['message' => 'Malformed HTTP request.']),
                ['content-type' => ['application/json']],
                400
            ));
        }
    }

    /**
     * Create a new Octane request from the incoming event.
     *
     * @param  array  $event
     * @return \Laravel\Octane\RequestContext
     */
    protected function request($event)
    {
        return OctaneRequestContextFactory::fromEvent($event, $this->serverVariables());
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
