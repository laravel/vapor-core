<?php

namespace Laravel\Vapor\Runtime;

use Laravel\Vapor\Runtime\Handlers\UnknownEventHandler;

class HttpHandlerFactory
{
    /**
     * Create a new handler for the given HTTP event.
     *
     * @param array $event
     * @return \Laravel\Vapor\Contracts\LambdaEventHandler
     */
    public static function make(array $event)
    {
        foreach (app()->tagged('http-handler') as $handler) {
            if ($handler->supports($event)) return $handler;
        }

        return new UnknownEventHandler;
    }
}
