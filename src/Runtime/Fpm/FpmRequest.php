<?php

namespace Laravel\Vapor\Runtime\Fpm;

use hollodotme\FastCGI\Interfaces\ProvidesRequestData;
use Illuminate\Support\Arr;

class FpmRequest implements ProvidesRequestData
{
    use ActsAsFastCgiDataProvider;

    /**
     * The request server variables.
     *
     * @var array
     */
    public $serverVariables;

    /**
     * The request body.
     *
     * @var string
     */
    public $body;

    /**
     * Create a new FPM request instance.
     *
     * @param  array  $serverVariables
     * @param  string  $body
     * @return void
     */
    public function __construct(array $serverVariables, $body)
    {
        $this->body = $body;
        $this->serverVariables = $serverVariables;
    }

    /**
     * Create a new FPM request from the given Lambda event.
     *
     * @param  array  $event
     * @param  string  $handler
     * @param  array  $serverVariables
     * @return static
     */
    public static function fromLambdaEvent(array $event, $handler, array $serverVariables = [])
    {
        [$uri, $queryString] = static::getUriAndQueryString($event);

        $headers = static::getHeaders($event);

        $requestBody = static::getRequestBody($event);

        $serverVariables = array_merge($serverVariables, [
            'GATEWAY_INTERFACE' => 'FastCGI/1.0',
            'PATH_INFO' => $event['path'] ?? '/',
            'QUERY_STRING' => $queryString,
            'REMOTE_ADDR' => '127.0.0.1',
            'REMOTE_PORT' => $headers['x-forwarded-port'] ?? 80,
            'REQUEST_METHOD' => $event['httpMethod'],
            'REQUEST_URI' => $uri,
            'SCRIPT_FILENAME' => $handler,
            'SERVER_ADDR' => '127.0.0.1',
            'SERVER_NAME' => $headers['host'] ?? 'localhost',
            'SERVER_PORT' => $headers['x-forwarded-port'] ?? 80,
            'SERVER_PROTOCOL' => $event['requestContext']['protocol'] ?? 'HTTP/1.1',
            'SERVER_SOFTWARE' => 'vapor',
        ]);

        [$headers, $serverVariables] = static::ensureContentTypeIsSet(
            $event, $headers, $serverVariables
        );

        [$headers, $serverVariables] = static::ensureContentLengthIsSet(
            $event, $headers, $serverVariables, $requestBody
        );

        $headers = static::ensureSourceIpAddressIsSet(
            $event, $headers
        );

        foreach ($headers as $header => $value) {
            $serverVariables['HTTP_'.strtoupper(str_replace('-', '_', $header))] = $value;
        }

        return new static($serverVariables, $requestBody);
    }

    /**
     * Get the URI and query string for the given event.
     *
     * @param  array  $event
     * @return array
     */
    protected static function getUriAndQueryString(array $event)
    {
        $uri = $event['path'] ?? '/';

        $queryString = self::getQueryString($event);

        parse_str($queryString, $queryParameters);

        return [
            empty($queryString) ? $uri :  $uri.'?'.$queryString,
            http_build_query($queryParameters)
        ];
    }

    /**
     * Get the query string from the event.
     *
     * @param  array $event
     * @return string
     */
    protected static function getQueryString(array $event)
    {
        if (! isset($event['multiValueQueryStringParameters'])) {
            return http_build_query(
                $event['queryStringParameters'] ?? []
            );
        }

        return http_build_query(
            collect($event['multiValueQueryStringParameters'] ?? [])
                ->mapWithKeys(function ($values, $key) use ($event) {
                    $key = ! isset($event['requestContext']['elb']) ? $key : urldecode($key);

                    return count($values) === 1
                        ? [$key => $values[0]]
                        : [(substr($key, -2) == '[]' ? substr($key, 0, -2) : $key) => $values];
                })->map(function ($values) use ($event) {
                    if (! isset($event['requestContext']['elb'])) {
                        return $values;
                    }

                    return ! is_array($values) ? urldecode($values) : array_map(function ($value) {
                        return urldecode($value);
                    }, $values);
                })->all()
        );
    }

    /**
     * Get the request headers from the event.
     *
     * @param  array  $event
     * @return array
     */
    protected static function getHeaders(array $event)
    {
        if (! isset($event['multiValueHeaders'])) {
            return array_change_key_case(
                $event['headers'] ?? [], CASE_LOWER
            );
        }

        return array_change_key_case(
            collect($event['multiValueHeaders'] ?? [])
                ->mapWithKeys(function ($headers, $name) {
                    return [$name => Arr::last($headers)];
                })->all(), CASE_LOWER
        );
    }

    /**
     * Get the request body from the event.
     *
     * @param  array  $event
     * @return string
     */
    protected static function getRequestBody(array $event)
    {
        $body = $event['body'] ?? '';

        return isset($event['isBase64Encoded']) && $event['isBase64Encoded']
                    ? base64_decode($body)
                    : $body;
    }

    /**
     * Ensure the request headers / server variables contain a content type.
     *
     * @param  array  $event
     * @param  array  $headers
     * @param  array  $serverVariables
     * @return array
     */
    protected static function ensureContentTypeIsSet(array $event, array $headers, array $serverVariables)
    {
        if ((strtoupper($event['httpMethod']) === 'POST') && ! isset($headers['content-type'])) {
            $headers['content-type'] = 'application/x-www-form-urlencoded';
        }

        if (isset($headers['content-type'])) {
            $serverVariables['CONTENT_TYPE'] = $headers['content-type'];
        }

        return [$headers, $serverVariables];
    }

    /**
     * Ensure the request headers / server variables contain a content length.
     *
     * @param  array  $event
     * @param  array  $headers
     * @param  array  $serverVariables
     * @param  string  $requestBody
     * @return array
     */
    protected static function ensureContentLengthIsSet(array $event, array $headers, array $serverVariables, $requestBody)
    {
        if (! in_array(strtoupper($event['httpMethod']), ['TRACE']) && ! isset($headers['content-length'])) {
            $headers['content-length'] = strlen($requestBody);
        }

        if (isset($headers['content-length'])) {
            $serverVariables['CONTENT_LENGTH'] = $headers['content-length'];
        }

        return [$headers, $serverVariables];
    }

    /**
     * Ensure the request headers contain a source IP address.
     *
     * @param  array  $event
     * @param  array  $headers
     * @return array
     */
    protected static function ensureSourceIpAddressIsSet(array $event, array $headers)
    {
        if (isset($event['requestContext']['identity']['sourceIp'])) {
            $headers['x-vapor-source-ip'] = $event['requestContext']['identity']['sourceIp'];
        }

        return $headers;
    }
}
