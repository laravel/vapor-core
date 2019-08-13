<?php

namespace Laravel\Vapor\Queue;

use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;

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
        return $this->runJob($job, $connectionName, $options);
    }
}
