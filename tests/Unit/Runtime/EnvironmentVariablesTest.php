<?php

namespace Laravel\Vapor\Tests\Unit\Runtime;

use Laravel\Vapor\Runtime\EnvironmentVariables;
use PHPUnit\Framework\TestCase;

class EnvironmentVariablesTest extends TestCase
{
    public function testInjectsEnvironmentVariables()
    {
        EnvironmentVariables::addToEnvironment(__DIR__.'/../../Fixtures/vaporEnvironmentVariables.php');

        $this->assertSame('https://my.cloudfront.net/asset-url', $_ENV['ASSET_URL']);
        $this->assertSame('https://my.cloudfront.net/mix-url', $_ENV['MIX_URL']);

        $this->assertSame('https://my.cloudfront.net/asset-url', $_SERVER['ASSET_URL']);
        $this->assertSame('https://my.cloudfront.net/mix-url', $_SERVER['MIX_URL']);
    }

    public function testDoesNotInjectsEnvironmentVariablesBecauseTheyAlreadyExist()
    {
        $_ENV['ASSET_URL'] = 'https://my.cloudfront.net/existing-asset-url';
        $_ENV['MIX_URL'] = 'https://my.cloudfront.net/existing-mix-url';

        $_SERVER['ASSET_URL'] = 'https://my.cloudfront.net/existing-asset-url';
        $_SERVER['MIX_URL'] = 'https://my.cloudfront.net/existing-mix-url';

        EnvironmentVariables::addToEnvironment(__DIR__.'/../../Fixtures/vaporEnvironmentVariables.php');

        $this->assertSame('https://my.cloudfront.net/existing-asset-url', $_ENV['ASSET_URL']);
        $this->assertSame('https://my.cloudfront.net/existing-mix-url', $_ENV['MIX_URL']);

        $this->assertSame('https://my.cloudfront.net/existing-asset-url', $_SERVER['ASSET_URL']);
        $this->assertSame('https://my.cloudfront.net/existing-mix-url', $_SERVER['MIX_URL']);
    }

    public function testDoesNotInjectsBecauseFileDoesNotExist()
    {
        EnvironmentVariables::addToEnvironment(__DIR__.'/../../Fixtures/FileDoesNotExist.php');

        $this->assertFalse(isset($_ENV['ASSET_URL']));
        $this->assertFalse(isset($_ENV['MIX_URL']));

        $this->assertFalse(isset($_SERVER['ASSET_URL']));
        $this->assertFalse(isset($_SERVER['MIX_URL']));
    }

    protected function tearDown(): void
    {
        unset($_ENV['ASSET_URL']);
        unset($_ENV['MIX_URL']);

        unset($_SERVER['ASSET_URL']);
        unset($_SERVER['MIX_URL']);
    }
}
