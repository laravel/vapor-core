<?php

namespace Laravel\Vapor\Tests\Unit;

use Carbon\Carbon;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Vapor\VaporServiceProvider;
use Mockery;
use Orchestra\Testbench\TestCase;

class VaporScheduleCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2021-01-01 00:00:00');

        Str::createUuidsUsing(function () {
            return 'test-schedule-lock-key';
        });
    }

    protected function getPackageProviders($app): array
    {
        return [
            VaporServiceProvider::class,
        ];
    }

    public function test_scheduler_is_invoked_when_invalid_cache_is_configured()
    {
        $fake = Mockery::mock(Repository::class);
        Cache::shouldReceive('getDefaultDriver')->once()->andReturn('array');
        $fake->shouldNotReceive('remember');
        if (version_compare($this->app->version(), 10, '>=')) {
            $fake->shouldReceive('forget')->once()->with('illuminate:schedule:interrupt')->andReturn(true);
        }
        if (version_compare($this->app->version(), 11, '>=')) {
            Cache::shouldReceive('driver')->twice()->andReturn($fake);
        } elseif (! Str::startsWith($this->app->version(), '9')) {
            Cache::shouldReceive('driver')->once()->andReturn($fake);
        }
        $fake->shouldNotReceive('forget')->with('vapor:schedule:lock');

        $this->artisan('vapor:schedule')
            ->assertExitCode(0);
    }

    public function test_scheduler_is_called_at_the_top_of_the_minute()
    {
        Cache::shouldReceive('getDefaultDriver')->once()->andReturn('dynamodb');
        Cache::shouldReceive('driver')->andReturn($fake = Mockery::mock(Repository::class));
        $fake->shouldReceive('remember')->once()->with('vapor:schedule:lock', 60, Mockery::any())->andReturn('test-schedule-lock-key');
        if (version_compare($this->app->version(), 10, '>=')) {
            $fake->shouldReceive('forget')->once()->with('illuminate:schedule:interrupt')->andReturn(true);
        }
        $fake->shouldReceive('forget')->once()->with('vapor:schedule:lock')->andReturn(true);

        $this->artisan('vapor:schedule')
            ->assertExitCode(0);
    }

    public function test_scheduler_is_not_invoked_if_lock_cannot_be_obtained()
    {
        Cache::shouldReceive('getDefaultDriver')->once()->andReturn('dynamodb');
        Cache::shouldReceive('driver')->andReturn($fake = Mockery::mock(Repository::class));
        $fake->shouldReceive('remember')->once()->with('vapor:schedule:lock', 60, Mockery::any())->andReturn('test-locked-schedule-lock-key');
        $fake->shouldNotReceive('forget')->with('illuminate:schedule:interrupt')->andReturn(true);
        $fake->shouldNotReceive('forget')->with('vapor:schedule:lock');

        $this->artisan('vapor:schedule')
            ->assertExitCode(1);
    }
}
