<?php

namespace Laravel\Vapor\Tests\Unit;

use Laravel\Vapor\Events\LambdaEvent;
use PHPUnit\Framework\TestCase;

class LambdaEventTest extends TestCase
{
    public function test_to_array()
    {
        $event = $this->getEvent();

        $this->assertIsArray($event->toArray());
    }

    public function test_array_access()
    {
        $event = $this->getEvent();

        $this->assertIsArray($event['Records']);

        $this->assertSame('58600123-d011-4d76-af5d-960159ca44aa', $event['Records.0.messageId']);
        $this->assertSame('1', $event['Records.0.attributes.ApproximateReceiveCount']);

        unset($event['Records']);
        $this->assertFalse(isset($event['Records']));

        $event['Records'] = [['messageId' => 'foo']];
        $this->assertTrue(isset($event['Records']));
        $this->assertSame('foo', $event['Records.0.messageId']);
    }

    public function getEvent()
    {
        return new LambdaEvent(json_decode(
            file_get_contents(__DIR__.'/../Fixtures/LambdaEvent.json'),
            true
        ));
    }
}
