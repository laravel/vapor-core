<?php

namespace Laravel\Vapor\Tests\Unit;

use Aws\Sqs\SqsClient;
use Illuminate\Container\Container;
use Laravel\Vapor\Queue\VaporQueue;
use Mockery;
use PHPUnit\Framework\TestCase;

class VaporQueueTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
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
        $queue->setContainer(new Container());
        $this->assertSame('attribute-value', $queue->push($job));
    }
}
