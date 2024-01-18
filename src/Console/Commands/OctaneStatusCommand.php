<?php

namespace Laravel\Vapor\Console\Commands;

use Illuminate\Console\Command;

class OctaneStatusCommand extends Command
{
    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'octane:status';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Get the current status of the Octane server';

    /**
     * Handle the command.
     *
     * @return void
     */
    public function handle()
    {
        $this->isEnvironmentRunningOnOctane()
            ? $this->info('Octane server is running.')
            : $this->info('Octane server is not running.');
    }

    /**
     * Determine if the environment is running on Octane.
     *
     * @return bool
     */
    protected function isEnvironmentRunningOnOctane()
    {
        return isset($_ENV['OCTANE_DATABASE_SESSION_TTL']);
    }
}
