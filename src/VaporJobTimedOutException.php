<?php

namespace Laravel\Vapor;

use Throwable;
use Exception;

class VaporJobTimedOutException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param Throwable|null $previous
     */
    public function __construct(Throwable $previous = null)
    {
        parent::__construct("A queued job has timed out. It will be retried again.", 0, $previous);
    }
}
