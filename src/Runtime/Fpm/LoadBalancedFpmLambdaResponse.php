<?php

namespace Laravel\Vapor\Runtime\Fpm;

use Laravel\Vapor\Runtime\Response;

class LoadBalancedFpmLambdaResponse extends FpmLambdaResponse
{
    /**
     * Convert the response to Load Balancer's supported format.
     *
     * @return array
     */
    public function toApiGatewayFormat()
    {
        $requiresEncoding = isset($this->headers['x-vapor-base64-encode'][0]);

        return [
            'isBase64Encoded' => $requiresEncoding,
            'statusCode' => $this->status,
            'statusDescription' => $this->status.' '.Response::statusText($this->status),
            'multiValueHeaders' => empty($this->headers) ? [] : $this->prepareHeaders($this->headers),
            'body' => $requiresEncoding ? base64_encode($this->body) : $this->body,
        ];
    }

    /**
     * Prepare the given response headers.
     *
     * @param  array  $responseHeaders
     * @return array
     */
    protected function prepareHeaders(array $responseHeaders)
    {
        $headers = [];

        foreach ($responseHeaders as $name => $values) {
            $headers[static::normalizeHeaderName($name)] = $values;
        }

        if (! isset($headers['Content-Type']) || empty($headers['Content-Type'])) {
            $headers['Content-Type'] = ['text/html'];
        }

        return $headers;
    }
}
