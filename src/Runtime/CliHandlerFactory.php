<?php

namespace Laravel\Vapor\Runtime;

use Laravel\Vapor\Runtime\Handlers\CliHandler;
use Laravel\Vapor\Runtime\Handlers\QueueHandler;

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
        $messageId = $event['Records'][0]['messageId'] ?? null;

        $job = json_decode($event['Records'][0]['body'] ?? '')->job ?? null;

        return $messageId && $job
                    ? new QueueHandler
                    : new CliHandler;
    }
}
