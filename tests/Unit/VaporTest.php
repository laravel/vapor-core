<?php

namespace Laravel\Vapor\Tests\Unit;

use Laravel\Vapor\Vapor;
use Orchestra\Testbench\TestCase;

class VaporTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        unset($_ENV['VAPOR_SSM_PATH']);
    }

    public function test_vapor_active()
    {
        $_ENV['VAPOR_SSM_PATH'] = '/my-project-production';

        $this->assertTrue(Vapor::active());
    }

    public function test_vapor_inactive()
    {
        $this->assertTrue(Vapor::inactive());
    }

    public function test_vapor_when_active()
    {
        $_ENV['VAPOR_SSM_PATH'] = '/my-project-production';

        $this->assertSame('active', Vapor::whenActive('active', 'inactive'));
    }

    public function test_vapor_when_active_with_callback()
    {
        $_ENV['VAPOR_SSM_PATH'] = '/my-project-production';

        $this->assertSame('active', Vapor::whenActive(function () {
            return 'active';
        }, 'inactive'));
    }

    public function test_vapor_when_inactive()
    {
        $this->assertSame('inactive', Vapor::whenInactive('inactive', 'active'));
    }

    public function test_vapor_when_inactive_with_callback()
    {
        $this->assertSame('inactive', Vapor::whenInactive(function () {
            return 'inactive';
        }, 'active'));
    }
}
