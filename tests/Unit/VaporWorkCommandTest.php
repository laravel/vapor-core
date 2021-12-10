<?php

namespace Laravel\Vapor\Tests\Unit;

use Laravel\Vapor\Events\LambdaEvent;
use Mockery;
use Orchestra\Testbench\TestCase;

class VaporWorkCommandTest extends TestCase
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

    public function test_command_can_be_called()
    {
        $this->assertFalse(FakeJob::$handled);

        $job = new FakeJob;

        $event = $this->getEvent();

        $event['Records.0.body'] = json_encode([
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

        $this->instance(LambdaEvent::class, $event);

        $this->artisan('vapor:work');

        $this->assertTrue(FakeJob::$handled);
    }

    /**
     * Get the package's service providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            \Laravel\Vapor\VaporServiceProvider::class,
        ];
    }

    /**
     * Define the environment.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
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

    public function getEvent()
    {
        return new LambdaEvent(json_decode(
            file_get_contents(__DIR__.'/../Fixtures/LambdaEvent.json'),
            true
        ));
    }
}
