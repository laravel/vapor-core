<?php

namespace Laravel\Vapor\Runtime\Http\Middleware;

use Laravel\Vapor\Runtime\LambdaEvent;

class EnsureLambdaEventIsBound
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
        if (isset($_ENV['LAMBDA_EVENT'])) {
            app()->bind(LambdaEvent::class, function () {
                return new LambdaEvent(
                    json_decode(base64_decode($_ENV['LAMBDA_EVENT']), true)
                );
            });
        }

        return $next($request);
    }
}
