<?php

namespace Laravel\Vapor\Console\Commands;

use Illuminate\Console\Command;

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
        return $this->info('Health check complete!');
    }
}
