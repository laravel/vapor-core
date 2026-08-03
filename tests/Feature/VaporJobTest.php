<?php

namespace Laravel\Vapor\Tests\Feature;

use Aws\Result;
use Aws\Sqs\SqsClient;
use Illuminate\Cache\ArrayStore;
use Laravel\Vapor\Queue\JobAttempts;
use Laravel\Vapor\Queue\VaporJob;
use Laravel\Vapor\Tests\TestCase;
use Laravel\Vapor\Tests\Unit\FakeJob;
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

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('cache.stores.sqs-payloads', ['driver' => 'array']);
    }

    public function test_job_release_attempts_without_cache()
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

    public function test_job_release_attempts_with_cache()
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

    public function test_job_release_keeps_the_payload_offloaded()
    {
        if (! property_exists(VaporJob::class, 'overflowStorage')) {
            $this->markTestSkipped('Requires Laravel 13.');
        }

        unset($_ENV['VAPOR_CACHE_JOB_ATTEMPTS']);

        $sqs = Mockery::mock(SqsClient::class);

        $store = $this->app['cache']->store('sqs-payloads');

        $store->put($pointer = 'laravel:sqs-payloads:my-job-uuid', json_encode(['attempts' => 1]));

        $sqs->shouldReceive('deleteMessage')->once()->with([
            'QueueUrl' => 'test-vapor-queue-url',
            'ReceiptHandle' => 'test-receipt-handle',
        ]);

        $sqs->shouldReceive('sendMessage')->once()->with([
            'QueueUrl' => 'test-vapor-queue-url',
            'MessageBody' => json_encode(['@pointer' => $pointer]),
            'DelaySeconds' => 0,
        ])->andReturn(new Result([
            'MessageId' => 'my-released-job-id',
        ]));

        $job = new VaporJob($this->app, $sqs, [
            'ReceiptHandle' => 'test-receipt-handle',
            'Body' => json_encode(['@pointer' => $pointer]),
            'MessageId' => 'my-job-id',
        ], 'sqs', 'test-vapor-queue-url', [
            'enabled' => true,
            'store' => 'sqs-payloads',
        ]);

        $job->release();

        $this->assertSame(json_encode(['attempts' => 2]), $store->get($pointer));
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

    public function test_job_attempts_priority()
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

    public function test_job_attempts_when_its_missing_from_payload()
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

    public function test_job_fire_without_cache()
    {
        unset($_ENV['VAPOR_CACHE_JOB_ATTEMPTS']);

        $sqs = Mockery::mock(SqsClient::class);

        $sqs->shouldReceive('deleteMessage')->once()->with([
            'QueueUrl' => 'test-vapor-queue-url',
            'ReceiptHandle' => 'my-job-receipt',
        ]);

        $job = new FakeJob;

        $job = new VaporJob($this->app, $sqs, [
            'ReceiptHandle' => 'my-job-receipt',
            'Body' => json_encode([
                'displayName' => FakeJob::class,
                'job' => 'Illuminate\Queue\CallQueuedHandler@call',
                'maxTries' => null,
                'timeout' => null,
                'timeoutAt' => null,
                'data' => [
                    'commandName' => FakeJob::class,
                    'command' => serialize($job),
                ],
                'attempts' => 3,
            ]),
            'MessageId' => 'my-job-id',
        ], 'sqs', 'test-vapor-queue-url');

        resolve(JobAttempts::class)->increment($job);
        resolve(JobAttempts::class)->increment($job);
        resolve(JobAttempts::class)->increment($job);
        resolve(JobAttempts::class)->increment($job);

        $job->fire();

        $this->assertSame(0, resolve(JobAttempts::class)->get($job));
    }

    public function test_job_fire_with_cache()
    {
        $_ENV['VAPOR_CACHE_JOB_ATTEMPTS'] = 'true';

        $sqs = Mockery::mock(SqsClient::class);

        $sqs->shouldReceive('deleteMessage')->once()->with([
            'QueueUrl' => 'test-vapor-queue-url',
            'ReceiptHandle' => 'my-job-receipt',
        ]);

        $job = new FakeJob;

        $job = new VaporJob($this->app, $sqs, [
            'ReceiptHandle' => 'my-job-receipt',
            'Body' => json_encode([
                'displayName' => FakeJob::class,
                'job' => 'Illuminate\Queue\CallQueuedHandler@call',
                'maxTries' => null,
                'timeout' => null,
                'timeoutAt' => null,
                'data' => [
                    'commandName' => FakeJob::class,
                    'command' => serialize($job),
                ],
                'attempts' => 3,
            ]),
            'MessageId' => 'my-job-id',
        ], 'sqs', 'test-vapor-queue-url');

        resolve(JobAttempts::class)->increment($job);
        resolve(JobAttempts::class)->increment($job);
        resolve(JobAttempts::class)->increment($job);
        resolve(JobAttempts::class)->increment($job);

        $job->fire();

        $this->assertSame(0, resolve(JobAttempts::class)->get($job));
    }
}
