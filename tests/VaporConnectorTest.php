<?php

namespace Laravel\Vapor\Tests;

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
}
