<?php

namespace Laravel\Vapor\Runtime\Http\Middleware;

use Illuminate\Http\RedirectResponse;

class RedirectStaticAssets
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
        if ($request->path() === 'favicon.ico') {
            return new RedirectResponse($_ENV['ASSET_URL'].'/favicon.ico', 302, [
                'Cache-Control' => 'public, max-age=3600',
            ]);
        }

        if ($request->path() === 'robots.txt') {
            return new RedirectResponse($_ENV['ASSET_URL'].'/robots.txt', 302, [
                'Cache-Control' => 'public, max-age=3600',
            ]);
        }

        return $next($request);
    }
}
