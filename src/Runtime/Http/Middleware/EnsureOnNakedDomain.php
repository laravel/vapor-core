<?php

namespace Laravel\Vapor\Runtime\Http\Middleware;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class EnsureOnNakedDomain
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
        if (strpos($request->getHost(), 'www.') === 0) {
            return new RedirectResponse(Str::replaceFirst(
                'www.', '', $request->fullUrl()
            ), 301);
        }

        return $next($request);
    }
}
