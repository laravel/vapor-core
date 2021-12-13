<?php

namespace Laravel\Vapor\Tests\Unit;

use Laravel\Vapor\Events\LambdaEvent;
use Mockery;
use Laravel\Vapor\Runtime\Handlers\QueueHandler;
use Orchestra\Testbench\TestCase;

class QueueHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeJob::$handled = false;
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_job_can_be_called()
    {
        $this->assertFalse(FakeJob::$handled);

        $job = new FakeJob;

        $event = $this->getEvent();

        $event['Records'][0]['body'] = json_encode([
            'displayName' => FakeJob::class,
            'job' => 'Illuminate\Queue\CallQueuedHandler@call',
            'maxTries' => null,
            'timeout' => null,
            'timeoutAt' => null,
            'data' => [
                'commandName' => FakeJob::class,
                'command' => serialize($job),
            ],
            'attempts' => 0,
        ]);

        QueueHandler::$app = $this->app;

        $queueHandler = new QueueHandler();

        $this->assertFalse(QueueHandler::$app->bound(LambdaEvent::class));
        $queueHandler->handle($event);
        $this->assertFalse(QueueHandler::$app->bound(LambdaEvent::class));
        $this->assertTrue(FakeJob::$handled);
    }

    protected function getPackageProviders($app)
    {
        return [
            \Laravel\Vapor\VaporServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('queue.connections.vapor', [
            'driver' => 'sqs',
            'key' => env('SQS_KEY', 'your-public-key'),
            'secret' => env('SQS_SECRET', 'your-secret-key'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'your-queue-name'),
            'region' => env('SQS_REGION', 'us-east-1'),
            'delay' => env('SQS_DELAY', 0),
            'tries' => env('SQS_TRIES', 0),
            'force' => env('SQS_FORCE', false),
        ]);
    }

    protected function getEvent()
    {
        return json_decode(
            file_get_contents(__DIR__.'/../Fixtures/lambdaEvent.json'),
            true
        );
    }
}
