<?php

namespace Laravel\Vapor\Runtime;

use Psr\Http\Message\ResponseInterface;

class PsrLambdaResponseFactory
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
            'headers' => $headers,
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
            $name = static::normalizeHeaderName($name);

            if ($name == 'Set-Cookie') {
                $headers = array_merge($headers, static::buildCookieHeaders($values));

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
    protected static function buildCookieHeaders(array $values)
    {
        $headers = [];

        foreach ($values as $index => $value) {
            $headers[static::cookiePermutation($index)] = $value;
        }

        return $headers;
    }

    /**
     * Calculate the permutation of Set-Cookie for the current index.
     *
     * @param  int  $index
     * @return string
     */
    protected static function cookiePermutation($index)
    {
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
    protected static function normalizeHeaderName($name)
    {
        return str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
    }
}
