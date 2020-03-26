<?php

namespace Laravel\Vapor\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\WorkerOptions;
use InvalidArgumentException;
use Laravel\Vapor\Queue\VaporJob;
use Laravel\Vapor\Queue\VaporWorker;

class VaporWorkCommand extends Command
{
    use WritesQueueEventMessages;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'vapor:work
                            {message : The Base64 encoded message payload}
                            {--delay=0 : The number of seconds to delay failed jobs}
                            {--timeout=0 : The number of seconds a child process can run}
                            {--tries=0 : Number of times to attempt a job before logging it failed}
                            {--force : Force the worker to run even in maintenance mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process a Vapor job';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * The queue worker instance.
     *
     * @var \Laravel\Vapor\Queue\VaporWorker
     */
    protected $worker;

    /**
     * Indicates if the worker is already listening for events.
     *
     * @var bool
     */
    protected static $listeningForEvents = false;

    /**
     * Create a new queue work command.
     *
     * @param  \Laravel\Vapor\Queue\VaporWorker  $worker
     * @return void
     */
    public function __construct(VaporWorker $worker)
    {
        parent::__construct();

        $this->worker = $worker;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->downForMaintenance()) {
            return;
        }

        if (! static::$listeningForEvents) {
            $this->listenForEvents();

            static::$listeningForEvents = true;
        }

        $this->worker->setCache($this->laravel['cache']->driver());

        return $this->worker->runVaporJob(
            $this->marshalJob($this->message()),
            'sqs',
            $this->gatherWorkerOptions()
        );
    }

    /**
     * Marshal the job with the given message ID.
     *
     * @param  array  $message
     * @return \Laravel\Vapor\Queue\VaporJob
     */
    protected function marshalJob(array $message)
    {
        $normalizedMessage = $this->normalizeMessage($message);

        $queue = $this->worker->getManager()->connection('sqs');

        return new VaporJob(
            $this->laravel, $queue->getSqs(), $normalizedMessage,
            'sqs', $this->queueUrl($message)
        );
    }

    /**
     * Normalize the casing of the message array.
     *
     * @param  array  $message
     * @return array
     */
    protected function normalizeMessage(array $message)
    {
        return [
            'MessageId' => $message['messageId'],
            'ReceiptHandle' => $message['receiptHandle'],
            'Body' => $message['body'],
            'Attributes' => $message['attributes'],
            'MessageAttributes' => $message['messageAttributes'],
        ];
    }

    /**
     * Get the decoded message payload.
     *
     * @return array
     */
    protected function message()
    {
        return tap(json_decode(base64_decode($this->argument('message')), true), function ($message) {
            if ($message === false) {
                throw new InvalidArgumentException("Unable to unserialize message.");
            }
        });
    }

    /**
     * Get the queue URL from the given message.
     *
     * @param  array  $message
     * @return string
     */
    protected function queueUrl(array $message)
    {
        $eventSourceArn = explode(':', $message['eventSourceARN']);

        return sprintf(
            'https://sqs.%s.amazonaws.com/%s/%s',
            $message['awsRegion'],
            $accountId = $eventSourceArn[4],
            $queue = $eventSourceArn[5]
        );
    }

    /**
     * Gather all of the queue worker options as a single object.
     *
     * @return \Illuminate\Queue\WorkerOptions
     */
    protected function gatherWorkerOptions()
    {
        return new WorkerOptions(
            $this->option('delay'), $memory = 512,
            $this->option('timeout'), $sleep = 0,
            $this->option('tries'), $this->option('force'),
            $stopWhenEmpty = false
        );
    }

    /**
     * Store a failed job event.
     *
     * @param  \Illuminate\Queue\Events\JobFailed  $event
     * @return void
     */
    protected function logFailedJob(JobFailed $event)
    {
        $this->laravel['queue.failer']->log(
            $event->connectionName, $event->job->getQueue(),
            $event->job->getRawBody(), $event->exception
        );
    }

    /**
     * Determine if the worker should run in maintenance mode.
     *
     * @return bool
     */
    protected function downForMaintenance()
    {
        if (! $this->option('force')) {
            return $this->laravel->isDownForMaintenance();
        }

        return false;
    }
}
