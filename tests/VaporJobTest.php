<?php

namespace Laravel\Vapor\Tests;

use Aws\Sqs\SqsClient;
use Illuminate\Container\Container;
use Laravel\Vapor\Queue\VaporConnector;
use Laravel\Vapor\Queue\VaporJob;
use Mockery;
use PHPUnit\Framework\TestCase;

class VaporJobTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function test_job_is_deleted_on_release_and_new_job_is_created()
    {
        $sqs = Mockery::mock(SqsClient::class);

        $sqs->shouldReceive('deleteMessage')->once()->with([
            'QueueUrl' => 'test-vapor-queue-url',
            'ReceiptHandle' => 'test-receipt-handle',
        ]);

        $sqs->shouldReceive('sendMessage')->once()->with([
            'QueueUrl' => 'test-vapor-queue-url',
            'MessageBody' => json_encode(['attempts' => 2]),
            'DelaySeconds' => 0,
        ]);

        $job = new VaporJob(new Container, $sqs, [
            'ReceiptHandle' => 'test-receipt-handle',
            'Body' => json_encode(['attempts' => 1]),
        ], 'sqs', 'test-vapor-queue-url');

        $job->release();
    }

    public function test_can_determine_job_attempts()
    {
        $client = (new VaporConnector)->connect([
            'driver' => 'sqs',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'prefix' => 'https://sqs.us-east-1.amazonaws.com/111111111',
            'queue' => 'test-queue',
            'region' => 'us-east-1',
        ]);

        $job = new VaporJob(new Container, $client->getSqs(), [
            'Body' => json_encode(['attempts' => 1]),
        ], 'sqs', 'test-vapor-queue-url');

        $this->assertEquals(2, $job->attempts());
    }
}
