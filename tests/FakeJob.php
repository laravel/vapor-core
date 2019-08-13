<?php

namespace Laravel\Vapor\Tests;

class FakeJob
{
    public static $handled = false;

    public function handle()
    {
        static::$handled = true;
    }
}
