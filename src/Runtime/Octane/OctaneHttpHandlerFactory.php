<?php

namespace Laravel\Vapor\Runtime\Octane;

use Laravel\Vapor\Runtime\Handlers\LoadBalancedOctaneHandler;
use Laravel\Vapor\Runtime\Handlers\OctaneHandler;
use Laravel\Vapor\Runtime\Handlers\PayloadFormatVersion2OctaneHandler;
use Laravel\Vapor\Runtime\Handlers\UnknownEventHandler;
use Laravel\Vapor\Runtime\Handlers\WarmerHandler;
use Laravel\Vapor\Runtime\Handlers\WarmerPingHandler;

class OctaneHttpHandlerFactory
{
    /**
     * Create a new handler for the given HTTP event.
     *
     * @param  array  $event
     * @return \Laravel\Vapor\Contracts\LambdaEventHandler
     */
    public static function make(array $event)
    {
        if (isset($event['vaporWarmer'])) {
            return new WarmerHandler;
        } elseif (isset($event['vaporWarmerPing'])) {
            return new WarmerPingHandler;
        } elseif (isset($event['requestContext']['elb'])) {
            return new LoadBalancedOctaneHandler;
        } elseif (isset($event['version']) && $event['version'] === '2.0') {
            return new PayloadFormatVersion2OctaneHandler;
        } elseif (isset($event['httpMethod']) || isset($event['requestContext']['http']['method'])) {
            return new OctaneHandler;
        }

        return new UnknownEventHandler;
    }
}
