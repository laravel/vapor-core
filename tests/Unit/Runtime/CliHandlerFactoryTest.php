<?php

namespace Laravel\Vapor\Tests\Unit\Runtime;

use Laravel\Vapor\Runtime\CliHandlerFactory;
use Laravel\Vapor\Runtime\Handlers\CliHandler;
use Laravel\Vapor\Runtime\Handlers\QueueHandler;
use Orchestra\Testbench\TestCase;

class CliHandlerFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        QueueHandler::$app = 'dummy';
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        CliHandlerFactory::createHandlersNormally();
    }

    public function test_custom_sqs_events_use_cli_handler()
    {
        $this->assertInstanceOf(CliHandler::class, CliHandlerFactory::make($this->getSQSCustomEvent()));
    }

    public function test_custom_non_sqs_lambda_events_use_cli_handler()
    {
        $this->assertInstanceOf(CliHandler::class, CliHandlerFactory::make($this->getCustomNonSQSLambdaEvent()));
    }

    public function test_laravel_jobs_use_queue_handler()
    {
        $this->assertInstanceOf(QueueHandler::class, CliHandlerFactory::make($this->getSQSJobEvent()));
    }

    public function test_custom_handler_factory_is_used_when_set()
    {
        CliHandlerFactory::createHandlerUsing(function ($event) {
            return new CliHandler;
        });

        $this->assertInstanceOf(CliHandler::class, CliHandlerFactory::make($this->getSQSJobEvent()));
    }

    public function test_custom_handler_factory_receives_event()
    {
        $receivedEvent = null;

        CliHandlerFactory::createHandlerUsing(function ($event) use (&$receivedEvent) {
            $receivedEvent = $event;

            return new CliHandler;
        });

        $expectedEvent = $this->getSQSJobEvent();
        CliHandlerFactory::make($expectedEvent);

        $this->assertSame($expectedEvent, $receivedEvent);
    }

    public function test_default_behavior_is_restored_after_reset()
    {
        CliHandlerFactory::createHandlerUsing(function ($event) {
            return new CliHandler;
        });

        CliHandlerFactory::createHandlersNormally();

        $this->assertInstanceOf(QueueHandler::class, CliHandlerFactory::make($this->getSQSJobEvent()));
    }

    protected function getCustomNonSQSLambdaEvent()
    {
        return json_decode(
            file_get_contents(__DIR__.'/../../Fixtures/customNonSQSLambdaEvent.json'),
            true
        );
    }

    protected function getSQSJobEvent()
    {
        return json_decode(
            file_get_contents(__DIR__.'/../../Fixtures/jobLambdaEventFromSQS.json'),
            true
        );
    }

    protected function getSQSCustomEvent()
    {
        return json_decode(
            file_get_contents(__DIR__.'/../../Fixtures/customLambdaEventFromSQS.json'),
            true
        );
    }
}
