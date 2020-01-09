<?php

namespace Laravel\Vapor\Tests;

use Illuminate\Support\Facades\Artisan;
use Laravel\Vapor\Runtime\CliHandlerFactory;
use Laravel\Vapor\Runtime\Handlers\CliHandler;
use Laravel\Vapor\Runtime\Handlers\UnknownEventHandler;
use Orchestra\Testbench\TestCase;

class CliHandlerFactoryTest extends TestCase
{
    public function test_it_handles_lambda_events_listed_in_vapor_cli_file()
    {
        $_ENV['LAMBDA_TASK_ROOT'] = __DIR__ . '/Fixtures';

        $event = [
            'Records' => [
                [
                    'eventSource' => 'aws:s3',
                ],
            ],
        ];

        $event = CliHandler::intercept($event);
        $handler = CliHandlerFactory::make($event);

        $this->assertTrue(is_a($handler, CliHandler::class));
    }

    public function test_it_doesnt_handle_lambda_events_not_listed_in_vapor_cli_file()
    {
        $_ENV['LAMBDA_TASK_ROOT'] = __DIR__ . '/Fixtures';

        $event = [
            'Records' => [
                [
                    'eventSource' => 'aws:sns',
                ],
            ],
        ];

        $event = CliHandler::intercept($event);
        $handler = CliHandlerFactory::make($event);

        $this->assertTrue(is_a($handler, UnknownEventHandler::class));
    }

    public function test_it_handles_lambda_events_listed_in_vapor_cli_file_and_runs_the_associated_command()
    {
        $_ENV['LAMBDA_TASK_ROOT'] = __DIR__ . '/Fixtures';

        $test = $this;

        Artisan::command('s3:command {payload}', function () use ($test) {
            $payload = json_decode(base64_decode($this->argument('payload')), true);

            $test->assertTrue($payload['Records'][0]['s3']['bucket']['name'] === 'test-bucket');
        });

        $event = [
            'Records' => [
                [
                    'eventSource' => 'aws:s3',
                    's3' => [
                        'bucket' => [
                            'name' => 'test-bucket',
                        ]
                    ]
                ],
            ],
        ];

        $event = CliHandler::intercept($event);
        
        $this->artisan($event['cli'])->assertExitCode(0);
    }
}
