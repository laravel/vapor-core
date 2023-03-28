<?php

namespace Laravel\Vapor\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Queue\ManuallyFailedException;
use Illuminate\Support\Str;

class VaporQueueListFailedCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'vapor:queue-failed
                            {--limit= : The number of failed jobs to return}
                            {--page=1 : The page of failed jobs to return}
                            {--id= : The job ID filter by}
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

        $options = collect($this->options())
            ->filter(function ($value, $option) {
                return ! is_null($value) && in_array($option, ['id', 'queue', 'query', 'start']);
            });

        $failedJobs = collect($failed)->filter(function ($job) use ($options) {
            return $options->every(function ($value, $option) use ($job) {
                return $this->filter($job, $option, $value);
            });
        });

        $total = count($failedJobs);

        $page = $this->option('page');
        $limit = $this->option('limit');

        if ($limit) {
            $failedJobs = $failedJobs->forPage($page, $limit);
        }

        $failedJobs = $failedJobs->map(function ($failed) {
            return array_merge((array) $failed, [
                'payload' => $failed->payload,
                'exception' => Str::limit($failed->exception, 1000),
                'name' => $this->extractJobName($failed->payload),
                'queue' => Str::afterLast($failed->queue, '/'),
                'message' => $this->extractMessage($failed->exception),
                'connection' => $failed->connection,
            ]);
        })->values()->toArray();

        $failedJobs = [
            'failed_jobs' => $failedJobs,
            'total' => $total,
            'from' => $limit ? ($page - 1) * $limit + 1 : 1,
            'to' => $limit ? min($page * $limit, $total) : $total,
            'has_next_page' => $limit && $total > $limit * $page,
            'has_previous_page' => $limit && $page > 1 && $total > $limit * ($page - 1),
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
     * Extract the failed job message from exception.
     *
     * @param  string  $exception
     * @return string
     */
    private function extractMessage($exception)
    {
        if (Str::startsWith($exception, ManuallyFailedException::class)) {
            $message = 'Manually failed';
        } else {
            [$_, $message] = explode(':', $exception);
            [$message] = explode(' in /', $message);
            [$message] = explode(' in closure', $message);
        }

        if (! empty($message)) {
            return trim($message);
        }

        return '';
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

    /**
     * Determine whether the given job matches the given filter.
     *
     * @param  stdClass  $job
     * @param  string  $option
     * @param  string  $value
     * @return bool
     */
    protected function filter($job, $option, $value)
    {
        if ($option === 'id') {
            return $job->id === $value;
        }

        if ($option === 'queue') {
            return Str::afterLast($job->queue, '/') === $value;
        }

        if ($option === 'query') {
            return Str::contains(json_encode($job), $value);
        }

        if ($option === 'start') {
            return Carbon::parse($job->failed_at)->timestamp >= $value;
        }

        return false;
    }
}
