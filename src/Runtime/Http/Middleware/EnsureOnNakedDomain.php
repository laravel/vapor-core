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
        if ('https://'.$request->getHttpHost() === $_ENV['APP_VANITY_URL']) {
            return $next($request);
        }

        if (config('vapor.redirect_to_root') === true &&
            strpos($request->getHost(), 'www.') === 0) {
            return new RedirectResponse(Str::replaceFirst(
                'www.', '', $request->fullUrl()
            ), 301);
        }

        if (config('vapor.redirect_to_root') === false) {
            $url = parse_url(config('app.url'));

            $nakedHost = preg_replace('#^www\.(.+\.)#i', '$1', $url[
                'host'
            ]);

            if ($request->getHost() === $nakedHost) {
                return new RedirectResponse(str_replace(
                    $request->getScheme().'://',
                    $request->getScheme().'://www.',
                    $request->fullUrl()
                ), 301);
            }
        }

        return $next($request);
    }
}
