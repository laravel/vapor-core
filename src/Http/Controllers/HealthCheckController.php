<?php

namespace Laravel\Vapor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Vapor\Contracts\HealthCheckController as HealthCheckControllerContract;

class HealthCheckController extends Controller implements HealthCheckControllerContract
{
    /**
     * Respond to a health check request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        return response('OK');
    }
}
