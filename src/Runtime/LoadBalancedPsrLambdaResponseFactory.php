<?php

namespace Laravel\Vapor\Runtime;

use Psr\Http\Message\ResponseInterface;

class LoadBalancedPsrLambdaResponseFactory
{
    /**
     * Create a new Lambda response array from the given PSR response.
     *
     * @param  \Psr\Http\Message\ResponseInterface  $response
     * @return \Laravel\Vapor\Runtime\ArrayLambdaResponse
     */
    public static function fromPsrResponse(ResponseInterface $response)
    {
        $response->getBody()->rewind();

        $headers = static::parseHeaders($response);

        $requiresEncoding = isset($headers['X-Vapor-Base64-Encode']);

        $body = $response->getBody()->getContents();

        return new ArrayLambdaResponse([
            'isBase64Encoded' => $requiresEncoding,
            'statusCode' => $response->getStatusCode(),
            'statusDescription' => $response->getStatusCode().' '.Response::statusText($response->getStatusCode()),
            'multiValueHeaders' => $headers,
            'body' => $requiresEncoding ? base64_encode($body) : $body,
        ]);
    }

    /**
     * Parse the headers for the outgoing response.
     *
     * @param  \Psr\Http\Message\ResponseInterface  $response
     * @return array
     */
    protected static function parseHeaders(ResponseInterface $response)
    {
        $headers = [];

        foreach ($response->getHeaders() as $name => $values) {
            $headers[static::normalizeHeaderName($name)] = $values;
        }

        if (! isset($headers['Content-Type']) || empty($headers['Content-Type'])) {
            $headers['Content-Type'] = ['text/html'];
        }

        return $headers;
    }

    /**
     * Normalize the given header name into studly-case.
     *
     * @param  string  $name
     * @return string
     */
    protected static function normalizeHeaderName($name)
    {
        return str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
    }
}
