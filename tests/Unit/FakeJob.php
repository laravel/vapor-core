<?php

namespace Laravel\Vapor\Tests\Unit;

class FakeJob
{
    public static $handled = false;

    public function handle()
    {
        static::$handled = true;
    }
}
