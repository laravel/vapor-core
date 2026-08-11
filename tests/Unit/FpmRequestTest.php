<?php

namespace Laravel\Vapor\Tests\Unit;

use Carbon\Carbon;
use Laravel\Vapor\Runtime\Fpm\FpmRequest;
use Mockery;
use PHPUnit\Framework\TestCase;

class FpmRequestTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2021-01-01 00:00:00');
    }

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

    public function test_api_gateway_v1_request_time_is_set()
    {
        $epoch = Carbon::now()->getPreciseTimestamp(3);

        $request = FpmRequest::fromLambdaEvent([
            'httpMethod' => 'GET',
            'requestContext' => [
                'requestTimeEpoch' => $epoch,
            ],
        ]);

        $this->assertSame($epoch, $request->serverVariables['AWS_API_GATEWAY_REQUEST_TIME']);
    }

    public function test_api_gateway_v2_request_time_is_set()
    {
        $epoch = Carbon::now()->getPreciseTimestamp(3);

        $request = FpmRequest::fromLambdaEvent([
            'httpMethod' => 'GET',
            'requestContext' => [
                'timeEpoch' => $epoch,
            ],
        ]);

        $this->assertSame($epoch, $request->serverVariables['AWS_API_GATEWAY_REQUEST_TIME']);
    }

    public function test_elb_request_time_is_not_set()
    {
        $request = FpmRequest::fromLambdaEvent([
            'httpMethod' => 'GET',
        ]);

        $this->assertArrayNotHasKey('AWS_API_GATEWAY_REQUEST_TIME', $request->serverVariables);
    }

    public function test_multi_value_cookie_header_preserves_all_cookies()
    {
        $request = FpmRequest::fromLambdaEvent([
            'httpMethod' => 'GET',
            'multiValueHeaders' => [
                'Cookie' => [
                    'cookie_a=abc',
                    'cookie_b=def',
                ],
            ],
        ]);

        $this->assertSame('cookie_a=abc; cookie_b=def', $request->serverVariables['HTTP_COOKIE']);
    }

    public function test_api_gateway_rest_modern_tls_payload_preserves_all_cookies()
    {
        $request = FpmRequest::fromLambdaEvent([
            'resource' => '/{proxy+}',
            'path' => '/api/shared/auth/who-am-i-cookie',
            'httpMethod' => 'GET',
            'headers' => [
                'cookie' => 'cookie_b=refresh-value',
            ],
            'multiValueHeaders' => [
                'cookie' => [
                    'cookie_a=access-value',
                    'cookie_b=refresh-value',
                ],
            ],
            'requestContext' => [
                'protocol' => 'HTTP/1.1',
            ],
        ]);

        $this->assertSame(
            'cookie_a=access-value; cookie_b=refresh-value',
            $request->serverVariables['HTTP_COOKIE']
        );
    }

    public function test_api_gateway_rest_legacy_tls_payload_preserves_all_cookies()
    {
        $request = FpmRequest::fromLambdaEvent([
            'resource' => '/{proxy+}',
            'path' => '/api/shared/auth/who-am-i-cookie',
            'httpMethod' => 'GET',
            'headers' => [
                'cookie' => 'cookie_a=access-value; cookie_b=refresh-value',
            ],
            'multiValueHeaders' => [
                'cookie' => [
                    'cookie_a=access-value; cookie_b=refresh-value',
                ],
            ],
            'requestContext' => [
                'protocol' => 'HTTP/1.1',
            ],
        ]);

        $this->assertSame(
            'cookie_a=access-value; cookie_b=refresh-value',
            $request->serverVariables['HTTP_COOKIE']
        );
    }
}
