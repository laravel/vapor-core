<?php

namespace Laravel\Vapor\Runtime\Http\Middleware;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureBinaryEncoding
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  callable  $next
     * @return mixed
     */
    public function handle($request, $next)
    {
        /** @var \Illuminate\Http\Response $response */
        $response = $next($request);

        if (static::isBase64EncodingRequired($response)) {
            $response->headers->set('X-Vapor-Base64-Encode', 'True');
        }

        return $response;
    }

    public static function isBase64EncodingRequired(Response $response) : bool
    {
        $contentType = strtolower($response->headers->get('Content-Type', 'text/html'));

        if (Str::startsWith($contentType, 'text/') || Str::contains($contentType, ['xml', 'json'])) {
            return false;
        }

        return true;
    }
}
