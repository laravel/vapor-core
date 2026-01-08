<?php

namespace Laravel\Vapor\Runtime;

use Laravel\Vapor\Runtime\Handlers\CliHandler;
use Laravel\Vapor\Runtime\Handlers\QueueHandler;

class CliHandlerFactory
{
    /**
     * The custom handler resolver callback.
     *
     * @var callable|null
     */
    protected static $customHandlerResolver;

    /**
     * Create a new handler for the given CLI event.
     *
     * @param  array  $event
     * @return mixed
     */
    public static function make(array $event)
    {
        return static::shouldHandleAsQueueJob($event)
                    ? new QueueHandler
                    : new CliHandler;
    }

    /**
     * Determine if the event should be handled as a queue job.
     *
     * @param  array  $event
     * @return bool
     */
    protected static function shouldHandleAsQueueJob(array $event)
    {
        if (static::$customHandlerResolver) {
            return call_user_func(static::$customHandlerResolver, $event);
        }

        $messageId = $event['Records'][0]['messageId'] ?? null;

        $job = json_decode($event['Records'][0]['body'] ?? '')->job ?? null;

        return $messageId && $job;
    }

    /**
     * Set a custom handler resolver callback.
     *
     * The callback should return true for queue jobs, false for CLI events.
     *
     * @param  callable  $callback
     * @return void
     */
    public static function resolveHandlerUsing(callable $callback)
    {
        static::$customHandlerResolver = $callback;
    }

    /**
     * Reset the handler resolver to its default behavior.
     *
     * @return void
     */
    public static function resolveHandlersNormally()
    {
        static::$customHandlerResolver = null;
    }
}
