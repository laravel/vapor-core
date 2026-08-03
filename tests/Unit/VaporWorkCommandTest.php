<?php

namespace Laravel\Vapor\Tests\Unit;

use Illuminate\Support\Facades\Config;
use Laravel\Vapor\Events\LambdaEvent;
use Laravel\Vapor\Queue\VaporJob;
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
        parent::tearDown();
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

    public function test_command_can_be_called_with_an_offloaded_payload()
    {
        if (! property_exists(VaporJob::class, 'overflowStorage')) {
            $this->markTestSkipped('Requires Laravel 13.');
        }

        Config::set([
            'cache.stores.sqs-payloads' => ['driver' => 'array'],
            'queue.connections.sqs.overflow' => [
                'enabled' => true,
                'store' => 'sqs-payloads',
            ],
        ]);

        $this->assertFalse(FakeJob::$handled);

        $job = new FakeJob;

        $this->app['cache']->store('sqs-payloads')->put($pointer = 'laravel:sqs-payloads:my-job-uuid', json_encode([
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
        ]));

        $event = $this->getEvent();

        $event['Records.0.body'] = json_encode(['@pointer' => $pointer]);

        $this->instance(LambdaEvent::class, $event);

        $this->artisan('vapor:work');

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
        return new LambdaEvent(json_decode(
            file_get_contents(__DIR__.'/../Fixtures/jobLambdaEventFromSQS.json'),
            true
        ));
    }
}
