<?php

namespace Laravel\Vapor;

use Illuminate\Contracts\Debug\ExceptionHandler;
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
    }
}
