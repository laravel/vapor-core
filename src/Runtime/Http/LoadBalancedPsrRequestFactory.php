<?php

namespace Laravel\Vapor\Runtime\Http;

class LoadBalancedPsrRequestFactory extends PsrRequestFactory
{
    /**
     * Get the server variables for the event.
     *
     * @param  array  $headers
     * @param  string  $queryString
     * @return array
     */
    protected function serverVariables(array $headers, string $queryString)
    {
        $variables = [
            'HTTPS' => 'on',
            'SERVER_PROTOCOL' => $this->protocolVersion(),
            'REQUEST_METHOD' => $this->method(),
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
            'QUERY_STRING' => $queryString,
            'DOCUMENT_ROOT' => getcwd(),
            'REQUEST_URI' => $this->uri(),
        ];

        if (isset($headers['Host'])) {
            $variables['HTTP_HOST'] = $headers['Host'];
        }

        return $variables;
    }

    /**
     * Get the HTTP protocol version for the event.
     *
     * @return string
     */
    protected function protocolVersion()
    {
        return '1.1';
    }

    /**
     * Get the HTTP method for the event.
     *
     * @return string
     */
    protected function method()
    {
        return $this->event['httpMethod'] ?? 'GET';
    }

    /**
     * Get the URI for the event.
     *
     * @return string
     */
    protected function uri()
    {
        return $this->event['path'] ?? '/';
    }

    /**
     * Get the query string for the event.
     *
     * @return string
     */
    protected function queryString()
    {
        return http_build_query(
            collect($this->event['multiValueQueryStringParameters'] ?? [])
                ->mapWithKeys(function ($values, $key) {
                    return count($values) === 1 ? [$key => $values[0]] : [$key => $values];
                })->all()
        );
    }

    /**
     * Get the HTTP headers for the event.
     *
     * @return array
     */
    protected function headers()
    {
        return collect($this->event['multiValueHeaders'] ?? [])
                ->mapWithKeys(function ($headers, $name) {
                    return [static::normalizeHeaderName($name) => $headers[0]];
                })->all();
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
