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

    public function test_format()
    {
        $formatter = new JsonFormatter();

        if (class_exists(LogRecord::class)) {
            $record = new LogRecord(
                new \DateTimeImmutable(),
                'channel-test',
                Level::Info,
                'message-test',
                ['context-test' => 'context-value'],
                ['extra-test' => 'extra-value']
            );
        } else {
            $record = [
                'datetime' => new \DateTimeImmutable(),
                'channel' => 'channel-test',
                'level' => 200,
                'level_name' => 'INFO',
                'message' => 'message-test',
                'context' => ['context-test' => 'context-value'],
                'extra' => ['extra-test' => 'extra-value'],
            ];
        }

        $_ENV['AWS_REQUEST_ID'] = '123456789';

        $record = $formatter->format($record);
        $this->assertJson($record);

        $record = json_decode($record, true);

        if (class_exists(LogRecord::class)) {
            $this->assertSame([
                'message' => 'message-test',
                'context' => [
                    'context-test' => 'context-value',
                    'aws_request_id' => '123456789',
                ],
                'level' => 200,
                'level_name' => 'INFO',
                'channel' => 'channel-test',
                'datetime' => $record['datetime'],
                'extra' => [
                    'extra-test' => 'extra-value',
                ],
            ], $record);
        } else {
            $this->assertSame([
                'datetime' => $record['datetime'],
                'channel' => 'channel-test',
                'level' => 200,
                'level_name' => 'INFO',
                'message' => 'message-test',
                'context' => [
                    'context-test' => 'context-value',
                    'aws_request_id' => '123456789',
                ],
                'extra' => [
                    'extra-test' => 'extra-value',
                ],
            ], $record);
        }
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
                'datetime' => new \DateTimeImmutable(),
                'channel' => 'channel-test',
                'level' => 200,
                'level_name' => 'INFO',
                'message' => 'message-test',
                'context' => ['foo' => 'bar'],
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
                'datetime' => new \DateTimeImmutable(),
                'channel' => 'channel-test',
                'level' => 200,
                'level_name' => 'INFO',
                'message' => 'message-test',
                'context' => [],
                'extra' => [],
            ];
        }

        $record = $formatter->format($record);
        $this->assertJson($record);

        $record = json_decode($record, true);
        $this->assertSame(['aws_request_id' => null], $record['context']);
    }
}
