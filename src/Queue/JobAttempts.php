<?php

namespace Laravel\Vapor\Queue;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Queue\Job;

class JobAttempts
{
    /**
     * The number of seconds job attempts should remain on cache.
     */
    const TTL = 1209600;

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
     * Determine if the job have been attempted before.
     *
     * @param  \Illuminate\Contracts\Queue\Job|string  $job
     * @return bool
     */
    protected function has($job)
    {
        return ! is_null($this->cache->get($this->key($job)));
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @param  \Illuminate\Contracts\Queue\Job|string  $job
     * @return int
     */
    public function get($job)
    {
        return (int) $this->cache->get($this->key($job), 0);
    }

    /**
     * Increment the number of times the job has been attempted.
     *
     * @param  \Illuminate\Contracts\Queue\Job  $job
     * @return void
     */
    public function increment($job)
    {
        if ($this->has($job)) {
            $this->cache->increment($this->key($job));

            return;
        }

        $this->cache->put($this->key($job), 1, static::TTL);
    }

    /**
     * Transfer the job attempts from one job to another.
     *
     * @param  \Illuminate\Contracts\Queue\Job|string  $from
     * @param  \Illuminate\Contracts\Queue\Job|string  $to
     * @return void
     */
    public function transfer($from, $to)
    {
        $this->cache->put($this->key($to), $this->get($from), static::TTL);

        $this->cache->forget($this->key($from));
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
