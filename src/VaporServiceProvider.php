<?php

namespace Laravel\Vapor;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Vapor\Console\Commands\VaporWorkCommand;
use Laravel\Vapor\Http\Controllers\SignedStorageUrlController;
use Laravel\Vapor\Queue\VaporConnector;

class VaporServiceProvider extends ServiceProvider
{
    use ConfiguresQueue, ConfiguresRedis, DefinesRoutes;

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->ensureRoutesAreDefined();

        if (($_ENV['VAPOR_SERVERLESS_DB'] ?? null) === 'true') {
            Schema::defaultStringLength(191);
        }

        if ($this->app->resolved('queue')) {
            call_user_func($this->queueExtender());
        } else {
            $this->app->afterResolving(
                'queue', $this->queueExtender()
            );
        }
    }

    /**
     * Get the queue extension callback.
     *
     * @return \Closure
     */
    protected function queueExtender()
    {
        return function () {
            Queue::extend('sqs', function () {
                return new VaporConnector;
            });
        };
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(
            Contracts\SignedStorageUrlController::class,
            SignedStorageUrlController::class
        );

        $this->ensureRedisIsConfigured();
        $this->ensureQueueIsConfigured();
        $this->ensureMixIsConfigured();

        $this->registerCommands();
    }

    /**
     * Ensure Laravel Mix is properly configured.
     *
     * @return void
     */
    protected function ensureMixIsConfigured()
    {
        if (isset($_ENV['MIX_URL'])) {
            Config::set('app.mix_url', $_ENV['MIX_URL']);
        }
    }

    /**
     * Register the Vapor console commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->app->singleton('command.vapor.work', function ($app) {
            return new VaporWorkCommand($app['queue.vaporWorker']);
        });

        $this->commands(['command.vapor.work']);
    }
}
