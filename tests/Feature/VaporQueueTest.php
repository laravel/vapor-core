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
                'data' => [
                    'commandName' => FakeJob::class,
                    'command' => serialize($job),
                ],
                'attempts' => 0,
            ];

            foreach ($subset as $key => $value) {
                $this->assertArrayHasKey($key, $messageBody);
                $this->assertSame($value, $messageBody[$key]);
            }

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
}
