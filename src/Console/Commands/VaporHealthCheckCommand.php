<?php

namespace Laravel\Vapor\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class VaporHealthCheckCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'vapor:health-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the health of the Vapor application';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->ensureBaseConfigurationFilesWereHarmonized();

        $this->ensureCacheIsWorking();

        return $this->info('Health check complete!');
    }

    /**
     * Ensure the configuration files were harmonized.
     *
     * @return void
     */
    protected function ensureBaseConfigurationFilesWereHarmonized()
    {
        if (! file_exists($filename = __DIR__.'/../../../../framework/config/cache.php')) {
            return;
        }

        $configuration = file_get_contents($filename);

        if (! Str::contains($configuration, "'key' => env('NULL_AWS_ACCESS_KEY_ID')")) {
            throw new Exception(
                'Laravel 11 or later requires the latest version of Vapor CLI.'
            );
        }
    }

    /**
     * Ensure cache calls are working as expected.
     *
     * @return void
     */
    protected function ensureCacheIsWorking()
    {
        Cache::get('vapor-health-check');
    }
}
