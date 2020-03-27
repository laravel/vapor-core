<?php

namespace Laravel\Vapor;

use Exception;
use Throwable;

class VaporJobTimedOutException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param string $name
     * @param Throwable|null $previous
     */
    public function __construct($name, Throwable $previous = null)
    {
        parent::__construct($name. ' has timed out. It will be retried again.', 0, $previous);
    }
}
