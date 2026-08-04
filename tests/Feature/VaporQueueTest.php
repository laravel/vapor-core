<?php

namespace Laravel\Vapor\Tests\Unit;

use Aws\Sqs\SqsClient;
use Illuminate\Cache\ArrayStore;
use Laravel\Vapor\Queue\JobAttempts;
use Laravel\Vapor\Queue\VaporQueue;
use Laravel\Vapor\Tests\TestCase;
use Laravel\Vapor\VaporServiceProvider;
use Mockery;

class VaporQueueTest extends TestCase
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

    public function test_proper_payload_array_is_created()
    {
        $sqs = Mockery::mock(SqsClient::class);

        $job = new FakeJob;

        $sqs->shouldReceive('sendMessage')->once()->with(Mockery::on(function ($argument) use ($job) {
            $messageBody = json_decode($argument['MessageBody'], true);

            $this->assertSame('/test-vapor-queue-url', $argument['QueueUrl']);

            $subset = [
                'displayName' => FakeJob::class,
                'job' => 'Illuminate\Queue\CallQueuedHandler@call',
                'maxTries' => null,
                'timeout' => null,
                'attempts' => 0,
            ];

            foreach ($subset as $key => $value) {
                $this->assertArrayHasKey($key, $messageBody);
                $this->assertSame($value, $messageBody[$key]);
            }

            $this->assertArrayHasKey('data', $messageBody);
            $this->assertSame(FakeJob::class, $messageBody['data']['commandName']);
            $this->assertSame(serialize($job), $messageBody['data']['command']);

            return true;
        }))->andReturnSelf();

        $sqs->shouldReceive('get')->andReturn('attribute-value');

        $queue = new VaporQueue($sqs, 'test-vapor-queue-url');
        $queue->setContainer($this->app);
        $this->assertSame('attribute-value', $queue->push($job));
    }

    public function test_queue_pop()
    {
        $_ENV['VAPOR_CACHE_JOB_ATTEMPTS'] = 'true';

        $sqs = Mockery::mock(SqsClient::class);

        $job = new FakeJob;

        $sqs->shouldReceive('receiveMessage')->once()->andReturn([
            'Messages' => [
                ['MessageId' => 'my-job-id'],
            ],
        ]);

        $queue = new VaporQueue($sqs, 'test-vapor-queue-url');
        $queue->setContainer($this->app);
        $job = $queue->pop();

        $this->assertSame(1, resolve(JobAttempts::class)->get('my-job-id'));
    }

    public function test_payload_is_offloaded_to_the_overflow_store()
    {
        if (! property_exists(VaporQueue::class, 'overflowStorage')) {
            $this->markTestSkipped('Requires Laravel 13.');
        }

        $sqs = Mockery::mock(SqsClient::class);

        $job = new FakeJob;

        $pointer = null;

        $sqs->shouldReceive('sendMessage')->once()->with(Mockery::on(function ($argument) use (&$pointer) {
            $messageBody = json_decode($argument['MessageBody'], true);

            $this->assertSame('/test-vapor-queue-url', $argument['QueueUrl']);
            $this->assertSame(['@pointer'], array_keys($messageBody));

            $pointer = $messageBody['@pointer'];

            return true;
        }))->andReturnSelf();

        $sqs->shouldReceive('get')->andReturn('attribute-value');

        $queue = new VaporQueue($sqs, 'test-vapor-queue-url', '', '', false, [
            'enabled' => true,
            'store' => 'sqs-payloads',
            'always' => true,
        ]);
        $queue->setContainer($this->app);
        $this->assertSame('attribute-value', $queue->push($job));

        $payload = json_decode($this->app['cache']->store('sqs-payloads')->get($pointer), true);

        $this->assertSame(FakeJob::class, $payload['data']['commandName']);
        $this->assertSame(serialize($job), $payload['data']['command']);
        $this->assertSame(0, $payload['attempts']);
    }

    public function test_popped_job_resolves_the_offloaded_payload()
    {
        if (! property_exists(VaporQueue::class, 'overflowStorage')) {
            $this->markTestSkipped('Requires Laravel 13.');
        }

        $_ENV['VAPOR_CACHE_JOB_ATTEMPTS'] = 'true';

        $sqs = Mockery::mock(SqsClient::class);

        $this->app['cache']->store('sqs-payloads')->put(
            $pointer = 'laravel:sqs-payloads:my-job-uuid', $payload = json_encode(['attempts' => 1])
        );

        $sqs->shouldReceive('receiveMessage')->once()->andReturn([
            'Messages' => [
                [
                    'MessageId' => 'my-job-id',
                    'Body' => json_encode(['@pointer' => $pointer]),
                ],
            ],
        ]);

        $queue = new VaporQueue($sqs, 'test-vapor-queue-url', '', '', false, [
            'enabled' => true,
            'store' => 'sqs-payloads',
        ]);
        $queue->setContainer($this->app);
        $job = $queue->pop();

        $this->assertSame($payload, $job->getRawBody());
    }
}
