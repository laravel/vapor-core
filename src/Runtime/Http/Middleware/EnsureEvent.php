<?php

namespace Laravel\Vapor\Runtime\Http\Middleware;

use Laravel\Vapor\Runtime\Event;

class EnsureEvent
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
        if (isset($_ENV['VAPOR_EVENT'])) {
            app()->bind(Event::class, function () {
                return new Event(
                    json_decode(base64_decode($_ENV['VAPOR_EVENT']), true)
                );
            });
        }

        return $next($request);
    }
}
