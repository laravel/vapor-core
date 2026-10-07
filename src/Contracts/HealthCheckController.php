<?php

namespace Laravel\Vapor\Contracts;

use Illuminate\Http\Request;

interface HealthCheckController
{
    /**
     * Respond to a health check request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request);
}
