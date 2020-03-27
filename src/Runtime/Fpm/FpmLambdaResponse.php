<?php

namespace Laravel\Vapor\Runtime\Fpm;

use Laravel\Vapor\Contracts\LambdaResponse;
use stdClass;

class FpmLambdaResponse implements LambdaResponse
{
    /**
     * The response status code.
     *
     * @var int
     */
    protected $statusCode = 200;

    /**
     * The response headers.
     *
     * @var array
     */
    protected $headers;

    /**
     * The response body.
     *
     * @var string
     */
    protected $body;

    /**
     * Create a new Lambda response from an FPM response.
     *
     * @param  int  $status
     * @param  array  $headers
     * @param  string  $body
     * @return void
     */
    public function __construct(int $status, array $headers, $body)
    {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
    }

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
                $headers = array_merge($headers, $this->buildCookieHeaders($values));

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

    /**
     * Build the Set-Cookie header names using binary casing.
     *
     * @param  array  $values
     * @return array
     */
    protected function buildCookieHeaders(array $values)
    {
        $headers = [];

        foreach ($values as $index => $value) {
            $headers[$this->cookiePermutation($index)] = $value;
        }

        return $headers;
    }

    /**
     * Calculate the permutation of Set-Cookie for the current index.
     *
     * @param  int  $index
     * @return string
     */
    protected function cookiePermutation($index)
    {
        // Hard-coded to support up to 18 cookies for now...
        switch ($index) {
            case 0:
                return 'set-cookie';
            case 1:
                return 'Set-cookie';
            case 2:
                return 'sEt-cookie';
            case 3:
                return 'seT-cookie';
            case 4:
                return 'set-Cookie';
            case 5:
                return 'set-cOokie';
            case 6:
                return 'set-coOkie';
            case 7:
                return 'set-cooKie';
            case 8:
                return 'set-cookIe';
            case 9:
                return 'set-cookiE';
            case 10:
                return 'SEt-cookie';
            case 11:
                return 'SET-cookie';
            case 12:
                return 'SEt-Cookie';
            case 13:
                return 'SEt-cOokie';
            case 14:
                return 'SEt-coOkie';
            case 15:
                return 'SEt-cooKie';
            case 16:
                return 'SEt-cookIe';
            case 17:
                return 'SEt-cookiE';
            default:
                return 'Set-Cookie';
        }
    }

    /**
     * Normalize the given header name into studly-case.
     *
     * @param  string  $name
     * @return string
     */
    protected function normalizeHeaderName($name)
    {
        return str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
    }
}
