<?php

namespace Laravel\Vapor\Runtime;

use Laravel\Vapor\Runtime\Handlers\CliHandler;
use Laravel\Vapor\Runtime\Handlers\QueueHandler;

class CliHandlerFactory
{
    /**
     * The custom handler factory callback.
     *
     * @var callable|null
     */
    protected static $customHandlerFactory;

    /**
     * Create a new handler for the given CLI event.
     *
     * @param  array  $event
     * @return mixed
     */
    public static function make(array $event)
    {
        if (static::$customHandlerFactory) {
            return call_user_func(static::$customHandlerFactory, $event);
        }

        $messageId = $event['Records'][0]['messageId'] ?? null;

        $job = json_decode($event['Records'][0]['body'] ?? '')->job ?? null;

        return $messageId && $job
                    ? new QueueHandler
                    : new CliHandler;
    }

    /**
     * Set a custom handler factory callback.
     *
     * @param  callable  $callback
     * @return void
     */
    public static function createHandlerUsing(callable $callback)
    {
        static::$customHandlerFactory = $callback;
    }

    /**
     * Reset the handler factory to its default behavior.
     *
     * @return void
     */
    public static function createHandlersNormally()
    {
        static::$customHandlerFactory = null;
    }
}
