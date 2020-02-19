<?php

ini_set('display_errors', '1');

error_reporting(E_ALL);

if (! file_exists('/tmp/opcache')) {
    mkdir('/tmp/opcache');
}

$appRoot = $_ENV['LAMBDA_TASK_ROOT'];

require $appRoot.'/vendor/autoload.php';

fwrite(STDERR, 'Loaded Composer autoload file');

/*
|--------------------------------------------------------------------------
| Bootstrap The Runtime
|--------------------------------------------------------------------------
|
| If the application is being served by the console layer, we will require in the
| console runtime. Otherwise, we will use the FPM runtime. Vapor will setup an
| environment variable for the console layer that we will use to check this.
|
*/

if (isset($_ENV['APP_RUNNING_IN_CONSOLE']) &&
    $_ENV['APP_RUNNING_IN_CONSOLE'] === 'true') {
    return require __DIR__.'/cliRuntime.php';
} else {
    return require __DIR__.'/fpmRuntime.php';
}
