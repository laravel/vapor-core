<?php

namespace Laravel\Vapor\Tests\Unit;

use Laravel\Vapor\Runtime\LoadBalancedLambdaResponse;
use PHPUnit\Framework\TestCase;

class LoadBalancedLambdaResponseTest extends TestCase
{
    public function test_header_values_are_always_strings()
    {
        $response = new LoadBalancedLambdaResponse(200, ['Foo-Bar' => [1]], 'body');

        $elbResponse = $response->toApiGatewayFormat();

        self::assertSame(['Foo-Bar' => ['1'], 'Content-Type' => ['text/html']], $elbResponse['multiValueHeaders']);
    }
}
