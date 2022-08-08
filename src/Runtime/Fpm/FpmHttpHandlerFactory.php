<?php

namespace Laravel\Vapor\Runtime\Fpm;

use Laravel\Vapor\Runtime\Handlers\FpmHandler;
use Laravel\Vapor\Runtime\Handlers\LoadBalancedFpmHandler;
use Laravel\Vapor\Runtime\Handlers\PayloadFormatVersion2FpmHandler;
use Laravel\Vapor\Runtime\Handlers\UnknownEventHandler;
use Laravel\Vapor\Runtime\Handlers\WarmerHandler;
use Laravel\Vapor\Runtime\Handlers\WarmerPingHandler;

class FpmHttpHandlerFactory
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
            return new LoadBalancedFpmHandler;
        } elseif (isset($event['version']) && $event['version'] === '2.0') {
            return new PayloadFormatVersion2FpmHandler;
        } elseif (isset($event['httpMethod']) || isset($event['requestContext']['http']['method'])) {
            return new FpmHandler;
        }

        return new UnknownEventHandler;
    }
}
