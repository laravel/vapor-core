<?php

namespace Laravel\Vapor\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class VaporListFailedCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'vapor:failed
                            {--limit=20 : The number of failed jobs to return}
                            {--offset=1 : The offset to start returning failed jobs}
                            {--queue= : The queue to filter by}
                            {--search= : The search term to filter by}
                            {--start= : The start time to filter by}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all of the failed queue jobs';

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
        $failed = $this->laravel['queue.failer']->all();

        $failedJobs = collect($failed)
            ->reverse();

        if ($queue = $this->option('queue')) {
            $failedJobs = $failedJobs->filter(function ($job) use ($queue) {
                return $queue == $job->queue;
            });
        }

        if ($search = $this->option('search')) {
            $failedJobs = $failedJobs->filter(function ($job) use ($search) {
                return Str::contains(json_encode($job), $search);
            });
        }

        if ($startTime = $this->option('start')) {
            $failedJobs = $failedJobs->filter(function ($job) use ($startTime) {
                return Carbon::parse($job->failed_at)->timestamp >= $startTime;
            });
        }

        $failedJobs = $failedJobs->forPage(
            $this->option('offset'),
            $this->option('limit')
        )->map(function ($failed) {
            return array_merge((array) $failed, [
                'payload' => Str::limit($failed->payload, 1000),
                'exception' => Str::limit($failed->exception, 1000),
                'name' => $this->extractJobName($failed->payload),
                'queue' => Str::afterLast($failed->queue, '/'),
                'exception_title' => Str::before($failed->exception, "\n"),
            ]);
        })->toJson();

        $this->output->writeln($failedJobs);
    }

    /**
     * Extract the failed job name from payload.
     *
     * @param  string  $payload
     * @return string|null
     */
    private function extractJobName($payload)
    {
        $payload = json_decode($payload, true);

        if ($payload && (! isset($payload['data']['command']))) {
            return $payload['job'] ?? null;
        } elseif ($payload && isset($payload['data']['command'])) {
            return $this->matchJobName($payload);
        }
    }

    /**
     * Match the job name from the payload.
     *
     * @param  array  $payload
     * @return string|null
     */
    protected function matchJobName($payload)
    {
        preg_match('/"([^"]+)"/', $payload['data']['command'], $matches);

        return $matches[1] ?? $payload['job'] ?? null;
    }
}
