<?php

namespace Laravel\Vapor\Tests\Unit;

use Laravel\Vapor\Runtime\LambdaEvent;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    public function test_to_array()
    {
        $event = $this->getEvent();

        $this->assertSame([
            'resource' => '/{proxy+}',
            'requestContext' => [
                'resourcePath' => '/{proxy+}',
            ],
        ], $event->toArray());
    }

    public function test_array_access()
    {
        $event = $this->getEvent();

        $this->assertSame('/{proxy+}', $event['resource']);
        $this->assertTrue(isset($event['resource']));

        $this->assertSame('/{proxy+}', $event['requestContext.resourcePath']);

        $this->assertTrue(is_array($event['requestContext']));

        unset($event['resource']);
        $this->assertFalse(isset($event['resource']));

        $event['resource'] = 'foo';
        $this->assertTrue(isset($event['resource']));
        $this->assertSame('foo', $event['resource']);
    }

    public function getEvent()
    {
        $event = '{"resource":"\/{proxy+}", "requestContext": { "resourcePath":"\/{proxy+}" }}';

        $event = base64_encode(json_encode(json_decode($event, true)));

        return new LambdaEvent(json_decode(base64_decode($event), true));
    }
}
