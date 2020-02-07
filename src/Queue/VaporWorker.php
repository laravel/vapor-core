<?php

namespace Laravel\Vapor\Queue;

use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Laravel\Vapor\VaporJobTimedOutException;

class VaporWorker extends Worker
{
    /**
     * Process the given job.
     *
     * @param  \Illuminate\Contracts\Queue\Job  $job
     * @param  string  $connectionName
     * @param  \Illuminate\Queue\WorkerOptions  $options
     * @return void
     */
    public function runVaporJob($job, $connectionName, WorkerOptions $options)
    {
        pcntl_async_signals(true);

        pcntl_signal(SIGALRM, function (){
            throw new VaporJobTimedOutException();
        });

        pcntl_alarm(
            max($this->timeoutForJob($job, $options), 0)
        );

        return $this->runJob($job, $connectionName, $options);
    }
}
