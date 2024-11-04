<?php

namespace Laravel\Vapor;

use Illuminate\Cache\NullStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Laravel\Vapor\Queue\JobAttempts;
use Laravel\Vapor\Queue\VaporWorker;

trait ConfiguresQueue
{
    /**
     * Ensure the queue / workers are configured.
     *
     * @return void
     */
    protected function ensureQueueIsConfigured()
    {
        if ($this->app->bound('queue.vaporWorker')) {
            return;
        }

        $this->app->singleton('queue.vaporWorker', function () {
            $isDownForMaintenance = function () {
                return $this->app->isDownForMaintenance();
            };

            return new VaporWorker(
                $this->app['queue'],
                $this->app['events'],
                $this->app[ExceptionHandler::class],
                $isDownForMaintenance
            );
        });

        $this->app->singleton(JobAttempts::class, function () {
            return new JobAttempts(
                isset($_ENV['VAPOR_CACHE_JOB_ATTEMPTS']) && $_ENV['VAPOR_CACHE_JOB_ATTEMPTS'] === 'true'
                    ? $this->app['cache']->driver()
                    : new Repository(new NullStore())
            );
        });
    }
}
