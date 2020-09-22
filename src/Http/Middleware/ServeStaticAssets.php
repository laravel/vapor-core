<?php

namespace Laravel\Vapor\Http\Middleware;

use Closure;
use GuzzleHttp\Client;

class ServeStaticAssets
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  ...$guards
     * @return mixed
     */
    public function handle($request, Closure $next, ...$guards)
    {
        $response = $next($request);

        if (isset($_ENV['VAPOR_SSM_PATH']) && $response->getStatusCode() === 404) {
            $requestUri = $request->getRequestUri();

            if (in_array(ltrim($requestUri, '/'), config('vapor.serve_assets', [])) !== false) {
                $asset = (new Client)->get(asset($requestUri));

                $headers = collect($asset->getHeaders())
                    ->only(['Content-Length', 'Content-Type'])
                    ->all();

                if ($asset->getStatusCode() === 200) {
                    return response($asset->getBody())->withHeaders($headers);
                }
            }
        }

        return $response;
    }
}
