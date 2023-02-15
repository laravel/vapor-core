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
                            {--limit= : The number of failed jobs to return}
                            {--offset=1 : The offset to start returning failed jobs}
                            {--queue= : The queue to filter by}
                            {--query= : The search query to filter by}
                            {--start= : The start timestamp to filter by}';

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
                return Str::afterLast($job->queue, '/') === $queue;
            });
        }

        if ($query = $this->option('query')) {
            $failedJobs = $failedJobs->filter(function ($job) use ($query) {
                return Str::contains(json_encode($job), $query);
            });
        }

        if ($startTime = $this->option('start')) {
            $failedJobs = $failedJobs->filter(function ($job) use ($startTime) {
                return Carbon::parse($job->failed_at)->timestamp >= $startTime;
            });
        }

        $total = count($failedJobs);

        $offset = $this->option('offset');
        $limit = $this->option('limit');

        if ($limit) {
            $failedJobs = $failedJobs->forPage($offset, $limit);
        }

        $failedJobs = $failedJobs->map(function ($failed) {
            return array_merge((array) $failed, [
                'payload' => Str::limit($failed->payload, 1000),
                'exception' => Str::limit($failed->exception, 1000),
                'name' => $this->extractJobName($failed->payload),
                'queue' => Str::afterLast($failed->queue, '/'),
                'exception_title' => Str::before($failed->exception, "\n"),
            ]);
        })->values()->toArray();

        $failedJobs = [
            'failed_jobs' => $failedJobs,
            'total' => $total,
            'has_next_page' => $limit && $total > $limit * $offset,
            'has_previous_page' => $limit && $offset > 1 && $total > $limit * ($offset - 1),
        ];

        $this->output->writeln(
            json_encode($failedJobs)
        );
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
