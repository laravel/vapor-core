<?php

namespace Laravel\Vapor\Runtime;

use Laravel\Vapor\Runtime\Octane\Octane;

class LambdaContainer
{
    /**
     * Terminate if the container has handled enough invocations.
     *
     * @param  int  $invocations
     * @param  int  $invocationLimit
     * @return void
     */
    public static function terminateIfInvocationLimitHasBeenReached($invocations, $invocationLimit)
    {
        if (empty($invocationLimit)) {
            return;
        }

        if ($invocations >= $invocationLimit) {
            Octane::terminate();

            echo 'Killing container. Container has processed '.$invocationLimit.' invocations. ('.$_ENV['AWS_REQUEST_ID'].')'.PHP_EOL;

            exit(0);
        }
    }
}
