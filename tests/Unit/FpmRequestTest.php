<?php

namespace Laravel\Vapor\Tests\Unit;

use Laravel\Vapor\Runtime\Fpm\FpmRequest;
use Mockery;
use PHPUnit\Framework\TestCase;

class FpmRequestTest extends TestCase
{
    protected function tearDown(): void
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
        ]);

        $this->assertSame(http_build_query(['Host' => urldecode($host)]), $request->serverVariables['QUERY_STRING']);
    }

    public function test_api_gateway_headers_are_handled()
    {
        $trace = ['Root=1-7696740c-c075312a25f21abe1ca19805;foobar'];
        $for = ['172.105.167.153', '70.132.20.166'];
        $port = ['443'];
        $proto = ['https'];

        $request = FpmRequest::fromLambdaEvent([
            'httpMethod' => 'GET',
            'multiValueHeaders' => [
                'X-Amzn-Trace-Id' => $trace,
                'X-Forwarded-For' => $for,
                'X-Forwarded-Port' => $port,
                'X-Forwarded-Proto' => $proto,
            ],
            'queryStringParameters' => null,
            'multiValueQueryStringParameters' => null,
        ]);

        $this->assertSame('Root=1-7696740c-c075312a25f21abe1ca19805;foobar', $request->serverVariables['HTTP_X_AMZN_TRACE_ID']);
        $this->assertSame('70.132.20.166', $request->serverVariables['HTTP_X_FORWARDED_FOR']);
        $this->assertSame('443', $request->serverVariables['HTTP_X_FORWARDED_PORT']);
        $this->assertSame('https', $request->serverVariables['HTTP_X_FORWARDED_PROTO']);
    }

    public function test_api_gateway_v2_headers_are_handled()
    {
        $trace = 'Root=1-7696740c-c075312a25f21abe1ca19805;foobar';
        $for = '172.105.167.153,70.132.20.166';
        $port = '443';
        $proto = 'https';

        $request = FpmRequest::fromLambdaEvent([
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'protocol' => 'HTTP/1.1',
                ],
            ],
            'headers' => [
                'x-amzn-trace-id' => $trace,
                'x-forwarded-for' => $for,
                'x-forwarded-port' => $port,
                'x-forwarded-proto' => $proto,
            ],
            'queryStringParameters' => null,
        ]);

        $this->assertSame('Root=1-7696740c-c075312a25f21abe1ca19805;foobar', $request->serverVariables['HTTP_X_AMZN_TRACE_ID']);
        $this->assertSame('172.105.167.153,70.132.20.166', $request->serverVariables['HTTP_X_FORWARDED_FOR']);
        $this->assertSame('443', $request->serverVariables['HTTP_X_FORWARDED_PORT']);
        $this->assertSame('https', $request->serverVariables['HTTP_X_FORWARDED_PROTO']);
    }

    public function test_api_gateway_v2_query_parameters_are_handled()
    {
        $request = FpmRequest::fromLambdaEvent([
            'version' => '2.0',
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'protocol' => 'HTTP/1.1',
                ],
            ],
            'queryStringParameters' => [
                'key1' => 'value1',
                'key2' => 'value2,value3',
            ],
        ]);

        $this->assertSame(
            http_build_query([
                'key1' => 'value1',
                'key2' => ['value2', 'value3'],
            ]),
            $request->serverVariables['QUERY_STRING']
        );
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
        ]);

        $this->assertSame($trace, $request->serverVariables['HTTP_X_AMZN_TRACE_ID']);
        $this->assertSame($for, $request->serverVariables['HTTP_X_FORWARDED_FOR']);
        $this->assertSame($port, $request->serverVariables['HTTP_X_FORWARDED_PORT']);
        $this->assertSame($proto, $request->serverVariables['HTTP_X_FORWARDED_PROTO']);
    }

    public function test_request_content_length_is_numeric()
    {
        $request = FpmRequest::fromLambdaEvent([
            'httpMethod' => 'GET',
            'headers' => [
                // ..
            ],
        ]);

        $this->assertSame(0, $request->getContentLength());

        $request = FpmRequest::fromLambdaEvent([
            'httpMethod' => 'GET',
            'headers' => [
                'content-length' => 1,
            ],
        ]);

        $this->assertSame(1, $request->getContentLength());

        $request = FpmRequest::fromLambdaEvent([
            'httpMethod' => 'GET',
            'headers' => [
                'content-length' => '1',
            ],
        ]);

        $this->assertSame(1, $request->getContentLength());

        $request = FpmRequest::fromLambdaEvent([
            'httpMethod' => 'GET',
            'headers' => [
                'content-length' => 'foo',
            ],
        ]);

        $this->assertSame(0, $request->getContentLength());
    }
}
