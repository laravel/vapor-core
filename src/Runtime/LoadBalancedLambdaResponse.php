<?php

namespace Laravel\Vapor\Runtime;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LoadBalancedLambdaResponse extends LambdaResponse
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
            'statusDescription' => $this->status.' '.$this->statusText($this->status),
            'multiValueHeaders' => empty($this->headers) ? [] : $this->prepareHeaders($this->headers),
            'body' => $requiresEncoding ? base64_encode($this->body) : $this->body,
        ];
    }

    /**
     * Get the status text for the given status code.
     *
     * @param  int  $status
     * @return string
     */
    public function statusText($status)
    {
        $statusTexts = SymfonyResponse::$statusTexts;

        $statusTexts[419] = 'Authentication Timeout';

        return $statusTexts[$status];
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
            $headers[static::normalizeHeaderName($name)] = static::normalizeHeaderValues($values);
        }

        if (! isset($headers['Content-Type']) || empty($headers['Content-Type'])) {
            $headers['Content-Type'] = ['text/html'];
        }

        return $headers;
    }

    /**
     * Normalize the given header values into strings.
     *
     * @param  array  $values
     * @return array
     */
    protected function normalizeHeaderValues($values)
    {
        return array_map(function ($value) {
            return (string) $value;
        }, $values);
    }
}
