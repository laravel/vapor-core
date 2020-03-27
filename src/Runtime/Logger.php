<?php

namespace Laravel\Vapor\Runtime;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;

class Logger
{
    /**
     * The logger instance.
     *
     * @var \Monolog\Logger
     */
    protected static $logger;

    /**
     * Write general information to the log.
     *
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public static function info($message, array $context = [])
    {
        static::ensureLoggerIsAvailable();

        static::$logger->info($message, $context);
    }

    /**
     * Write error information to the log.
     *
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public static function error($message, array $context = [])
    {
        static::ensureLoggerIsAvailable();

        static::$logger->error($message, $context);
    }

    /**
     * Ensure the logger has been instantiated.
     *
     * @return void
     */
    protected static function ensureLoggerIsAvailable()
    {
        if (isset(static::$logger)) {
            return;
        }

        static::$logger = new MonologLogger('vapor', [
            (new StreamHandler('php://stderr'))->setFormatter(new JsonFormatter)
        ]);
    }
}
