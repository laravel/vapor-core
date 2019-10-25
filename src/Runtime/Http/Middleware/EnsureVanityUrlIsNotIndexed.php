<?php

namespace Laravel\Vapor\Runtime\Http\Middleware;

class EnsureVanityUrlIsNotIndexed
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
        $response = $next($request);

        if ('https://'.$request->getHttpHost() === $_ENV['APP_VANITY_URL']) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow', true);
        }

        return $response;
    }
}
