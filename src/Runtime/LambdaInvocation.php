<?php

namespace Laravel\Vapor\Runtime;

use Exception;

class LambdaInvocation
{
    /**
     * The cached curl handler.
     *
     * @var resource
     */
    protected static $handler;

    /**
     * Get the next Lambda invocation ID and body.
     *
     * @param  string  $apiUrl
     * @return array
     */
    public static function next($apiUrl)
    {
        if (is_null(static::$handler)) {
            static::$handler = curl_init("http://{$apiUrl}/2018-06-01/runtime/invocation/next");

            curl_setopt(static::$handler, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt(static::$handler, CURLOPT_FAILONERROR, true);
        }

        // Retrieve the Lambda invocation ID...
        $invocationId = '';

        curl_setopt(static::$handler, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$invocationId) {
            if (! preg_match('/:\s*/', $header)) {
                return strlen($header);
            }

            [$name, $value] = preg_split('/:\s*/', $header, 2);

            if (strtolower($name) === 'lambda-runtime-aws-request-id') {
                $invocationId = trim($value);
            }

            return strlen($header);
        });

        // Retrieve the Lambda invocation event body...
        $body = '';

        curl_setopt(static::$handler, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$body) {
            $body .= $chunk;

            return strlen($chunk);
        });

        curl_exec(static::$handler);

        static::ensureNoErrorsOccurred(
            $invocationId, $body
        );

        return [$invocationId, json_decode($body, true)];
    }

    /**
     * Ensure no errors occurred retrieving the invocation.
     *
     * @param  string  $invocationId
     * @param  string  $body
     * @return void
     */
    protected static function ensureNoErrorsOccurred($invocationId, $body)
    {
        if (curl_error(static::$handler)) {
            throw new Exception('Failed to retrieve the next Lambda invocation: '.curl_error(static::$handler));
        }

        if ($invocationId === '') {
            throw new Exception('Failed to parse the Lambda invocation ID.');
        }

        if ($body === '') {
            throw new Exception('The Lambda runtime API returned an empty response.');
        }
    }
}
