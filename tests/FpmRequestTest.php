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

    public function test_api_gateway_headers_are_handled()
    {
        $trace = 'Root=1-7696740c-c075312a25f21abe1ca19805;foobar';
        $for = '172.105.167.153, 70.132.20.166';
        $port = '443';
        $proto = 'https';

        $request = FpmRequest::fromLambdaEvent([
            'httpMethod' => 'GET',
            'headers' => [
                'X-Amzn-Trace-Id' => $trace,
                'X-Forwarded-For' => $for,
                'X-Forwarded-Port' => $port,
                'X-Forwarded-Proto' => $port
            ],
            'multiValueHeaders' => [
                'X-Amzn-Trace-Id' => [
                    $trace,
                ],
                'X-Forwarded-For' => [
                    $for,
                ],
                'X-Forwarded-Port' => [
                    $port,
                ],
                'X-Forwarded-Proto' => [
                    $proto,
                ],
            ],
            'queryStringParameters' => null,
            'multiValueQueryStringParameters' => null,
        ], 'index.php');

        $this->assertEquals($trace, $request->serverVariables['HTTP_X_AMZN_TRACE_ID']);
        $this->assertEquals($for, $request->serverVariables['HTTP_X_FORWARDED_FOR']);
        $this->assertEquals($port, $request->serverVariables['HTTP_X_FORWARDED_PORT']);
        $this->assertEquals($proto, $request->serverVariables['HTTP_X_FORWARDED_PROTO']);
    }

    public function test_load_balancer_headers_are_over_spoofed_headers()
    {
        $request = FpmRequest::fromLambdaEvent([
            'requestContext' => [
                'elb' => [
                    'targetGroupArn' => 'arn:aws:elasticloadbalancing:us-west-2:308264878215:targetgroup/vapor-staging/2aa8690968087e6e',
                ],
            ],
            'httpMethod' => 'GET',
            'multiValueQueryStringParameters' => [],
            'multiValueHeaders' => [
                'x-amzn-trace-id' => [
                    'foobar',
                    $trace = 'Root=1-7696740c-c075312a25f21abe1ca19805;foobar',
                ],
                'x-forwarded-for' => [
                    '8.8.8.8',
                    $for = '8.8.8.8, 172.105.167.153',
                ],
                'x-forwarded-port' => [
                    '69',
                    $port = '443',
                ],
                'x-forwarded-proto' => [
                    'http',
                    $proto = 'https',
                ],
            ],
        ], 'index.php');

        $this->assertEquals($trace, $request->serverVariables['HTTP_X_AMZN_TRACE_ID']);
        $this->assertEquals($for, $request->serverVariables['HTTP_X_FORWARDED_FOR']);
        $this->assertEquals($port, $request->serverVariables['HTTP_X_FORWARDED_PORT']);
        $this->assertEquals($proto, $request->serverVariables['HTTP_X_FORWARDED_PROTO']);
    }
}
