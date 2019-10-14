<?php

namespace Laravel\Vapor\Runtime;

use Laravel\Vapor\Runtime\Handlers\UnknownEventHandler;

class CliHandlerFactory
{
    /**
     * Create a new handler for the given CLI event.
     *
     * @param array $event
     * @return mixed
     */
    public static function make(array $event)
    {
        foreach (app()->tagged('cli-handler') as $cliHandler) {
            if ($cliHandler->supports($event)) return $cliHandler;
        }

        return new UnknownEventHandler;
    }
}
