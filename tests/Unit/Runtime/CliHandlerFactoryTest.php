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
