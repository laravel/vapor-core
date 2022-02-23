<?php

namespace Laravel\Vapor\Queue;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Queue\Job;

class JobAttempts
{
    /**
     * The cache repository implementation.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * Create a new job attempts instance.
     *
     * @param  \Illuminate\Contracts\Cache\Repository  $cache
     * @return void
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Increment the number of times the job has been attempted.
     *
     * @param  \Illuminate\Contracts\Queue\Job  $job
     * @return void
     */
    public function increment($job)
    {
        $this->cache->increment($this->key($job));
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @param  \Illuminate\Contracts\Queue\Job|string  $job
     * @return int
     */
    public function get($job)
    {
        return $this->cache->get($this->key($job), 0);
    }

    /**
     * Forget the number of times the job has been attempted.
     *
     * @param  \Illuminate\Contracts\Queue\Job|string  $job
     * @return int
     */
    public function forget($job)
    {
        $this->cache->forget($this->key($job));
    }

    /**
     * Transfer the job attemps.
     *
     * @param  \Illuminate\Contracts\Queue\Job|string  $from
     * @param  \Illuminate\Contracts\Queue\Job|string  $to
     * @return void
     */
    public function transfer($from, $to)
    {
        $this->cache->put($this->key($to), $this->get($from));

        $this->cache->forget($this->key($from));
    }

    /**
     * Gets the cache key for the given job.
     *
     * @param  \Illuminate\Contracts\Queue\Job|string  $job
     * @return string
     */
    protected function key($job)
    {
        $jobId = $job instanceof Job ? $job->getJobId() : $job;

        return 'laravel_vapor_job_attemps:'.$jobId;
    }
}
