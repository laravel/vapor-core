<?php

namespace Laravel\Vapor\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;

class VaporScheduleCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'vapor:schedule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the scheduled commands at the beginning of every minute';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $cache = $this->ensureValidCacheDriver()) {
            $this->call('schedule:run');

            return 0;
        }

        $key = (string) Str::uuid();
        $lockObtained = false;

        while (true) {
            if (! $lockObtained) {
                $lockObtained = $this->obtainLock($cache, $key);
            }

            if ($lockObtained && now()->second === 0) {
                $this->releaseLock($cache);

                $this->call('schedule:run');

                return 0;
            }

            if (! $lockObtained && now()->second === 0) {
                return 1;
            }

            usleep(10000);
        }
    }

    /**
     * Ensure the cache driver is valid.
     */
    protected function ensureValidCacheDriver(): ?Repository
    {
        $manager = $this->laravel['cache'];

        if (in_array($manager->getDefaultDriver(), ['memcached', 'redis', 'dynamodb', 'database'])) {
            return $manager->driver();
        }

        return null;
    }

    /**
     * Obtain the lock for the schedule.
     */
    protected function obtainLock(Repository $cache, string $key): bool
    {
        return $key === $cache->remember('vapor:schedule:lock', 60, function () use ($key) {
            return $key;
        });
    }

    /**
     * Release the lock for the schedule.
     */
    protected function releaseLock(Repository $cache): void
    {
        $cache->forget('vapor:schedule:lock');
    }
}
