<?php

namespace Laravel\Vapor\Runtime;

use Laravel\Vapor\Runtime\Handlers\CliHandler;
use Laravel\Vapor\Runtime\Handlers\QueueHandler;
use Laravel\Vapor\Runtime\Handlers\UnknownEventHandler;

class CliHandlerFactory
{
    /**
     * Create a new handler for the given CLI event.
     *
     * @param  array  $event
     * @return mixed
     */
    public static function make(array $event)
    {
        if (isset($event['cli'])) {
            return new CliHandler;
        } elseif (isset($event['Records'][0]['messageId'])) {
            return new QueueHandler;
        } else {
            return new UnknownEventHandler;
        }
    }
}
