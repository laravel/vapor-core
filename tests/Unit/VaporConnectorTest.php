<?php

namespace Laravel\Vapor\Tests\Unit;

use Laravel\Vapor\Queue\VaporConnector;
use Laravel\Vapor\Queue\VaporQueue;
use PHPUnit\Framework\TestCase;

class VaporConnectorTest extends TestCase
{
    public function test_can_create_vapor_queue()
    {
        $queue = (new VaporConnector)->connect([
            'driver' => 'sqs',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'prefix' => 'https://sqs.us-east-1.amazonaws.com/111111111',
            'queue' => 'test-queue',
            'region' => 'us-east-1',
        ]);

        $this->assertInstanceOf(VaporQueue::class, $queue);
    }

    public function test_overflow_storage_options_are_passed_to_the_queue()
    {
        if (! property_exists(VaporQueue::class, 'overflowStorage')) {
            $this->markTestSkipped('Requires Laravel 13.');
        }

        $queue = (new VaporConnector)->connect([
            'driver' => 'sqs',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'prefix' => 'https://sqs.us-east-1.amazonaws.com/111111111',
            'queue' => 'test-queue',
            'region' => 'us-east-1',
            'overflow' => [
                'enabled' => true,
                'store' => 'sqs-payloads',
            ],
        ]);

        $this->assertSame([
            'enabled' => true,
            'store' => 'sqs-payloads',
        ], $queue->getOverflowStorage());
    }

    public function test_overflow_storage_options_default_to_an_empty_array()
    {
        $queue = (new VaporConnector)->connect([
            'driver' => 'sqs',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'prefix' => 'https://sqs.us-east-1.amazonaws.com/111111111',
            'queue' => 'test-queue',
            'region' => 'us-east-1',
        ]);

        $this->assertSame([], $queue->getOverflowStorage());
    }
}
