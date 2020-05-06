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
        if (config('vapor.redirect_to_root') &&
            strpos($request->getHost(), 'www.') === 0) {
            return new RedirectResponse(Str::replaceFirst(
                'www.', '', $request->fullUrl()
            ), 301);
        }

        if (! config('vapor.redirect_to_root') &&
            strpos($request->getHost(), 'www.') === false) {
            return new RedirectResponse(
                'www.'.$request->fullUrl(),
                301
            );
        }

        return $next($request);
    }
}
