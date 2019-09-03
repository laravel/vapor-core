<?php

namespace Laravel\Vapor\Tests;

use Mockery;
use PHPUnit\Framework\TestCase;
use Laravel\Vapor\Runtime\Fpm\FpmRequest;

class FpmRequestTest extends TestCase
{
    public function tearDown() : void
    {
        Mockery::close();
    }


    public function test_query_string_is_decoded_for_elb_requests()
    {
        $request = FpmRequest::fromLambdaEvent([
            'httpMethod' => 'GET',
            'multiValueQueryStringParameters' => [
                'Host' => [$host = '2000-01-01%2000%3A00%3A00'],
            ],
            'requestContext' => [
                'elb' => true,
            ],
        ], 'index.php');

        $this->assertEquals(http_build_query(['Host' => urldecode($host)]), $request->serverVariables['QUERY_STRING']);
    }
}
