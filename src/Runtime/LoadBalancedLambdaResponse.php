<?php

namespace Laravel\Vapor\Runtime;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
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
        $this->headers = empty($this->headers) ? [] : $this->prepareHeaders($this->headers);

        return [
            'body' => $this->prepareBody(),
            'multiValueHeaders' => $this->headers,
            'isBase64Encoded' => $this->isEncodingRequired(),

            'statusCode' => $this->status,
            'statusDescription' => $this->status.' '.$this->statusText($this->status),
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

    public function isEncodingRequired()
    {
        return isset($this->headers['x-vapor-base64-encode'][0]) || $this->isGzipCompatible();
    }

    public function isGzipCompatible()
    {
        $allowedContentTypes = [
            'text/html',
            'text/plain',
            'text/css',
            'text/javascript',
            'text/xml',
            'application/json',
            'application/javascript',
            'application/xml',
            'application/xml+rss',
        ];

        foreach ($allowedContentTypes as $contentType) {
            if (Str::contains($this->headers['Content-Type'][0], $contentType)) {
                return true;
            }
        }

        return false;
    }

    public function prepareBody()
    {
        if (!$this->isEncodingRequired()) {
            return $this->body;
        }

        if ($this->isGzipCompatible()) {
            $this->headers['Content-Encoding'] = ['gzip'];
            $this->body = gzencode($this->body, 9);
        }

        return base64_encode($this->body);
    }
}
