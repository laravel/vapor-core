<?php

namespace Laravel\Vapor\Tests;

use Mockery;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Illuminate\Contracts\Console\Kernel;
use Laravel\Vapor\Runtime\Handlers\CliHandler;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

class CliHandlerTest extends TestCase
{
    public function tearDown() : void
    {
        Mockery::close();
    }


    public function test_command_can_be_handled()
    {
        $app = Mockery::mock('Illuminate\Foundation\Application');

        $app->shouldReceive('useStoragePath')->once()->with('/tmp/storage');
        $app->shouldReceive('storagePath')->andReturn('/tmp/storage');

        $app->shouldReceive('make')->with(Kernel::class)->andReturn(
            $kernel = Mockery::mock(Kernel::class)
        );

        $app->shouldReceive('resolved')->andReturn(false);

        $kernel->shouldReceive('handle')
                ->with(Mockery::type(StringInput::class), BufferedOutput::class)
                ->andReturn(0);

        $kernel->shouldReceive('terminate')->once();

        $handler = new CliHandler($app, Mockery::mock(Logger::class)->shouldIgnoreMissing());
        $response = $handler->handle(['cli' => 'inspire'], new BufferedOutput);

        $response = $response->toApiGatewayFormat();

        $this->assertTrue(is_array($response));
        $this->assertTrue(isset($response['output']));
        $this->assertEquals(0, $response['statusCode']);
    }
}
