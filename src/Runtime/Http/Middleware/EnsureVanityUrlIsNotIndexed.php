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

        return $request->getHttpHost() === $_ENV['APP_VANITY_URL']
                    ? $response->header('X-Robots-Tag', 'noindex, nofollow')
                    : $response;
    }
}
