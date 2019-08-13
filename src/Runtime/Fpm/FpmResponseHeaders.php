<?php

namespace Laravel\Vapor\Runtime\Fpm;

class FpmResponseHeaders
{
    /**
     * Extract the response headers from the raw response.
     *
     * @param  string  $body
     * @return array
     */
    public static function fromBody($body)
    {
        $headers = [];

        foreach (explode(PHP_EOL, $body) as $line) {
            if (preg_match('#^([^\:]+):(.*)$#', $line, $matches)) {
                $headers[trim($matches[1])][] = trim($matches[2]);

                continue;
            }

            break;
        }

        return $headers;
    }
}
