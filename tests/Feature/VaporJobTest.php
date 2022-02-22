<?php

namespace Laravel\Vapor\Tests\Feature;

use Aws\Result;
use Aws\Sqs\SqsClient;
use Illuminate\Cache\ArrayStore;
use Laravel\Vapor\Queue\JobAttempts;
use Laravel\Vapor\Queue\VaporJob;
use Laravel\Vapor\Tests\TestCase;
use Laravel\Vapor\VaporServiceProvider;
use Mockery;

class VaporJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton('cache.store', ArrayStore::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            VaporServiceProvider::class,
        ];
    }

    public function test_job_is_deleted_on_release_and_new_job_is_created_without_cache()
    {
        unset($_ENV['VAPOR_CACHE_JOB_ATTEMPTS']);

        $sqs = Mockery::mock(SqsClient::class);

        $sqs->shouldReceive('deleteMessage')->once()->with([
            'QueueUrl' => 'test-vapor-queue-url',
            'ReceiptHandle' => 'test-receipt-handle',
        ]);

        $sqs->shouldReceive('sendMessage')->once()->with([
            'QueueUrl' => 'test-vapor-queue-url',
            'MessageBody' => json_encode(['attempts' => 2]),
            'DelaySeconds' => 0,
        ])->andReturn(new Result([
            'MessageId' => 'my-released-job-id',
        ]));

        $job = new VaporJob($this->app, $sqs, [
            'ReceiptHandle' => 'test-receipt-handle',
            'Body' => json_encode(['attempts' => 1]),
            'MessageId' => 'my-job-id',
        ], 'sqs', 'test-vapor-queue-url');

        $job->release();

        $this->assertSame(0, resolve(JobAttempts::class)->get('my-job-id'));
        $this->assertSame(0, resolve(JobAttempts::class)->get('my-released-job-id'));
    }

    public function test_job_is_deleted_on_release_and_new_job_is_created_with_cache()
    {
        $_ENV['VAPOR_CACHE_JOB_ATTEMPTS'] = 'true';

        $sqs = Mockery::mock(SqsClient::class);

        $sqs->shouldReceive('deleteMessage')->once()->with([
            'QueueUrl' => 'test-vapor-queue-url',
            'ReceiptHandle' => 'test-receipt-handle',
        ]);

        $sqs->shouldReceive('sendMessage')->once()->with([
            'QueueUrl' => 'test-vapor-queue-url',
            'MessageBody' => json_encode(['attempts' => 2]),
            'DelaySeconds' => 0,
        ])->andReturn(new Result([
            'MessageId' => 'my-released-job-id',
        ]));

        $job = new VaporJob($this->app, $sqs, [
            'ReceiptHandle' => 'test-receipt-handle',
            'Body' => json_encode(['attempts' => 1]),
            'MessageId' => 'my-job-id',
        ], 'sqs', 'test-vapor-queue-url');

        // Equivalent to ['attempts' => 1]
        resolve(JobAttempts::class)->increment($job);
        resolve(JobAttempts::class)->increment($job);

        $job->release();

        $this->assertSame(0, resolve(JobAttempts::class)->get('my-job-id'));
        $this->assertSame(2, resolve(JobAttempts::class)->get('my-released-job-id'));
    }

    public function test_job_attempts_without_cache()
    {
        unset($_ENV['VAPOR_CACHE_JOB_ATTEMPTS']);

        $sqs = Mockery::mock(SqsClient::class);

        $job = new VaporJob($this->app, $sqs, [
            'Body' => json_encode(['attempts' => 1]),
            'MessageId' => 'my-job-id',
        ], 'sqs', 'test-vapor-queue-url');

        resolve(JobAttempts::class)->increment($job);
        resolve(JobAttempts::class)->increment($job);
        resolve(JobAttempts::class)->increment($job);
        resolve(JobAttempts::class)->increment($job);

        $this->assertSame(2, $job->attempts());
        $this->assertSame(0, resolve(JobAttempts::class)->get($job));
    }

    public function test_job_attempts_with_cache()
    {
        $_ENV['VAPOR_CACHE_JOB_ATTEMPTS'] = 'true';

        $sqs = Mockery::mock(SqsClient::class);

        $job = new VaporJob($this->app, $sqs, [
            'Body' => json_encode(['attempts' => 1]),
            'MessageId' => 'my-job-id',
        ], 'sqs', 'test-vapor-queue-url');

        resolve(JobAttempts::class)->increment($job);
        resolve(JobAttempts::class)->increment($job);
        resolve(JobAttempts::class)->increment($job);
        resolve(JobAttempts::class)->increment($job);

        $this->assertSame(4, $job->attempts());
        $this->assertSame(4, resolve(JobAttempts::class)->get($job));
    }

    public function test_highest_attempts_takes_priority()
    {
        $_ENV['VAPOR_CACHE_JOB_ATTEMPTS'] = 'true';

        $sqs = Mockery::mock(SqsClient::class);

        $job = new VaporJob($this->app, $sqs, [
            'Body' => json_encode(['attempts' => 2]),
            'MessageId' => 'my-job-id',
        ], 'sqs', 'test-vapor-queue-url');

        resolve(JobAttempts::class)->increment($job);
        resolve(JobAttempts::class)->increment($job);

        $this->assertSame(3, $job->attempts());

        $job = new VaporJob($this->app, $sqs, [
            'Body' => json_encode(['attempts' => 0]),
            'MessageId' => 'my-other-job-id',
        ], 'sqs', 'test-vapor-queue-url');

        resolve(JobAttempts::class)->increment($job);
        resolve(JobAttempts::class)->increment($job);

        $this->assertSame(2, $job->attempts());
    }

    public function test_handles_job_missing_attempts()
    {
        unset($_ENV['VAPOR_CACHE_JOB_ATTEMPTS']);

        $sqs = Mockery::mock(SqsClient::class);

        $job = new VaporJob($this->app, $sqs, [
            'Body' => json_encode([]),
            'MessageId' => 'my-job-id',
        ], 'sqs', 'test-vapor-queue-url');

        $this->assertSame(1, $job->attempts());
    }

    public function test_job_attempts_on_delete()
    {
        $_ENV['VAPOR_CACHE_JOB_ATTEMPTS'] = 'true';

        $sqs = Mockery::mock(SqsClient::class);

        $sqs->shouldReceive('deleteMessage')->once()->with([
            'QueueUrl' => 'test-vapor-queue-url',
            'ReceiptHandle' => 'my-job-receipt',
        ]);

        $job = new VaporJob($this->app, $sqs, [
            'Body' => json_encode(['attempts' => 0]),
            'MessageId' => 'my-job-id',
            'ReceiptHandle' => 'my-job-receipt',
        ], 'sqs', 'test-vapor-queue-url');

        resolve(JobAttempts::class)->increment($job);
        resolve(JobAttempts::class)->increment($job);
        resolve(JobAttempts::class)->increment($job);

        $this->assertSame(3, $job->attempts());

        $job->delete();

        $this->assertSame(0, resolve(JobAttempts::class)->get($job));
    }
}
