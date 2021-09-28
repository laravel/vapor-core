<?php

namespace Laravel\Vapor\Runtime\Octane;

use Illuminate\Support\Collection;
use Laravel\Octane\RequestContext;
use Laravel\Vapor\Runtime\Request;
use Nyholm\Psr7\ServerRequest;

class OctaneRequestContextFactory
{
    /**
     * Creates an octane request context from the given request.
     *
     * @param  array  $event
     * @param  array  $serverVariables
     * @return \Laravel\Octane\RequestContext
     */
    public static function fromEvent($event, $serverVariables)
    {
        $request = Request::fromLambdaEvent($event, $serverVariables);

        $serverRequest = new ServerRequest(
            $request->serverVariables['REQUEST_METHOD'],
            $request->serverVariables['REQUEST_URI'],
            $request->headers,
            $request->body,
            $request->serverVariables['SERVER_PROTOCOL'],
            $request->serverVariables
        );

        $serverRequest = $serverRequest->withCookieParams(static::cookies($request->headers));

        return new RequestContext([
            'psr7Request' => $serverRequest,
        ]);
    }

    /**
     * Get the cookies from the given headers.
     *
     * @param  array  $headers
     * @return array
     */
    protected static function cookies($headers)
    {
        $headers = array_change_key_case($headers);

        if (! isset($headers['cookie'])) {
            return [];
        }

        return Collection::make(explode('; ', $headers['cookie']))->mapWithKeys(function ($cookie) {
            [$key, $value] = explode('=', trim($cookie), 2);

            return [$key => urldecode($value)];
        })->all();
    }
}
