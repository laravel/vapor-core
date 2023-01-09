<?php

namespace Laravel\Vapor\Tests\Unit\Logging;

use Laravel\Vapor\Logging\JsonFormatter;
use Mockery;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class JsonFormatterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        unset($_ENV['AWS_REQUEST_ID']);
    }

    public function test_includes_aws_request_id()
    {
        $formatter = new JsonFormatter();

        $_ENV['AWS_REQUEST_ID'] = '123456789';

        if (class_exists(LogRecord::class)) {
            $record = new LogRecord(
                new \DateTimeImmutable(),
                'channel-test',
                Level::Info,
                'message-test',
                ['foo' => 'bar']
            );
        } else {
            $record = [
                'message' => 'test',
                'context' => ['foo' => 'bar'],
                'level' => 200,
                'level_name' => 'INFO',
                'channel' => 'test',
                'datetime' => new \DateTimeImmutable(),
                'extra' => [],
            ];
        }

        $record = $formatter->format($record);
        $this->assertJson($record);

        $record = json_decode($record, true);
        $this->assertSame(['foo' => 'bar', 'aws_request_id' => '123456789'], $record['context']);
    }

    public function test_aws_request_id_may_be_null()
    {
        $formatter = new JsonFormatter();

        if (class_exists(LogRecord::class)) {
            $record = new LogRecord(
                new \DateTimeImmutable(),
                'channel-test',
                Level::Info,
                'message-test'
            );
        } else {
            $record = [
                'message' => 'test',
                'context' => ['foo' => 'bar'],
                'level' => 200,
                'level_name' => 'INFO',
                'channel' => 'test',
                'datetime' => new \DateTimeImmutable(),
                'extra' => [],
            ];
        }

        $record = $formatter->format($record);
        $this->assertJson($record);

        $record = json_decode($record, true);
        $this->assertSame(['aws_request_id' => null], $record['context']);
    }
}
