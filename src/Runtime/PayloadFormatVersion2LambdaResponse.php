<?php

namespace Laravel\Vapor\Runtime;

use Laravel\Vapor\Contracts\LambdaResponse as LambdaResponseContract;
use stdClass;

class PayloadFormatVersion2LambdaResponse extends LambdaResponse implements LambdaResponseContract
{
    /**
     * Convert the response to API Gateway's supported format.
     *
     * @return array
     */
    public function toApiGatewayFormat()
    {
        $requiresEncoding = isset($this->headers['x-vapor-base64-encode'][0]);

        return [
            'isBase64Encoded' => $requiresEncoding,
            'statusCode' => $this->status,
            'cookies' => isset($this->headers['set-cookie']) ? $this->headers['set-cookie'] : [],
            'headers' => empty($this->headers) ? new stdClass : $this->prepareHeaders($this->headers),
            'body' => $requiresEncoding ? base64_encode($this->body) : $this->body,
        ];
    }

    /**
     * Prepare the given response headers for API Gateway.
     *
     * @param  array  $responseHeaders
     * @return array
     */
    protected function prepareHeaders(array $responseHeaders)
    {
        $headers = [];

        foreach ($responseHeaders as $name => $values) {
            $name = $this->normalizeHeaderName($name);

            if ($name == 'Set-Cookie') {
                continue;
            }

            foreach ($values as $value) {
                $headers[$name] = $value;
            }
        }

        if (! isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'text/html';
        }

        return $headers;
    }
}
