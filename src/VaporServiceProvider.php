<?php

namespace Laravel\Vapor;

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Laravel\Vapor\Console\Commands\OctaneStatusCommand;
use Laravel\Vapor\Console\Commands\VaporHealthCheckCommand;
use Laravel\Vapor\Console\Commands\VaporQueueListFailedCommand;
use Laravel\Vapor\Console\Commands\VaporWorkCommand;
use Laravel\Vapor\Http\Controllers\SignedStorageUrlController;
use Laravel\Vapor\Http\Middleware\ServeStaticAssets;
use Laravel\Vapor\Queue\VaporConnector;

class VaporServiceProvider extends ServiceProvider
{
    use ConfiguresAssets, ConfiguresDynamoDb, ConfiguresQueue, ConfiguresRedis, ConfiguresSqs, DefinesRoutes;

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->ensureRoutesAreDefined();
        $this->registerOctaneCommands();

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

        $this->configure();
        $this->offerPublishing();
        $this->ensureAssetPathsAreConfigured();
        $this->ensureRedisIsConfigured();
        $this->ensureDynamoDbIsConfigured();
        $this->ensureQueueIsConfigured();
        $this->ensureSqsIsConfigured();
        $this->ensureMixIsConfigured();
        $this->configureTrustedProxy();

        $this->registerMiddleware();
        $this->registerCommands();
    }

    /**
     * Setup the configuration for Horizon.
     *
     * @return void
     */
    protected function configure()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/vapor.php', 'vapor'
        );
    }

    /**
     * Setup the resource publishing groups for Horizon.
     *
     * @return void
     */
    protected function offerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/vapor.php' => config_path('vapor.php'),
            ], 'vapor-config');
        }
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
     * Configure trusted proxy.
     *
     * @return void
     */
    private function configureTrustedProxy()
    {
        Config::set('trustedproxy.proxies', Config::get('trustedproxy.proxies') ?? ['0.0.0.0/0', '2000:0:0:0:0:0:0:0/3']);
    }

    /**
     * Register the Vapor HTTP middleware.
     *
     * @return void
     */
    protected function registerMiddleware()
    {
        $this->app[HttpKernel::class]->pushMiddleware(ServeStaticAssets::class);
    }

    /**
     * Register the Vapor console commands.
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function registerCommands()
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->app[ConsoleKernel::class]->command('vapor:handle {payload}', function () {
            throw new InvalidArgumentException(
                'Unknown event type. Please create a vapor:handle command to handle custom events.'
            );
        });

        $this->app->singleton('command.vapor.work', function ($app) {
            return new VaporWorkCommand($app['queue.vaporWorker']);
        });

        $this->app->singleton('command.vapor.queue-failed', function () {
            return new VaporQueueListFailedCommand;
        });

        $this->app->singleton('command.vapor.health-check', function () {
            return new VaporHealthCheckCommand;
        });

        $this->commands(['command.vapor.work', 'command.vapor.queue-failed', 'command.vapor.health-check']);
    }

    /**
     * Register the Vapor "Octane" console commands.
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function registerOctaneCommands()
    {
        // Ensure we are running on Vapor...
        if (! isset($_ENV['VAPOR_SSM_PATH'])) {
            return;
        }

        if ($this->app->runningInConsole()) {
            $this->commands(OctaneStatusCommand::class);
        }
    }
}
