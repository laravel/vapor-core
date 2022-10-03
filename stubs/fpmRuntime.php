<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Laravel\Vapor\Runtime\Environment;
use Laravel\Vapor\Runtime\Fpm\Fpm;
use Laravel\Vapor\Runtime\Fpm\FpmHttpHandlerFactory;
use Laravel\Vapor\Runtime\LambdaContainer;
use Laravel\Vapor\Runtime\LambdaRuntime;
use Laravel\Vapor\Runtime\Secrets;
use Laravel\Vapor\Runtime\StorageDirectories;

$app = require __DIR__.'/bootstrap/app.php';

$console = $app->make(ConsoleKernelContract::class);

/*
|--------------------------------------------------------------------------
| Inject SSM Secrets Into Environment
|--------------------------------------------------------------------------
|
| Next, we will inject any of the application's secrets stored in AWS
| SSM into the environment variables. These variables may be a bit
| larger than the variables allowed by Lambda which has a limit.
|
*/

fwrite(STDERR, 'Preparing to add secrets to runtime'.PHP_EOL);

$secrets = Secrets::addToEnvironment(
    $_ENV['VAPOR_SSM_PATH'],
    json_decode($_ENV['VAPOR_SSM_VARIABLES'] ?? '[]', true),
    __DIR__.'/vaporSecrets.php'
);

/*
|--------------------------------------------------------------------------
| Inject decrypted environment variables
|--------------------------------------------------------------------------
|
| Next, we will check to see whether a decryption key has been set on the
| environment. If so, we will attempt to discover an encrypted file at
| the root of the application and decrypt it into the Vapor runtime.
|
*/

fwrite(STDERR, 'Attempting to decrypt environment variables into runtime'.PHP_EOL);

Environment::decrypt($app);

/*
|--------------------------------------------------------------------------
| Cache Configuration
|--------------------------------------------------------------------------
|
| To give the application a speed boost, we are going to cache all of the
| configuration files into a single file. The file will be loaded once
| by the runtime then it will read the configuration values from it.
|
*/

StorageDirectories::create();

$app->useStoragePath(StorageDirectories::PATH);

fwrite(STDERR, 'Caching Laravel configuration'.PHP_EOL);

$console->call('config:cache');

/*
|--------------------------------------------------------------------------
| Cache Routes
|--------------------------------------------------------------------------
|
| To further boost performance, we will attempt to cache the routes of the
| application. Doing so will drastically decrease the amount of time it
| takes to register all of the routes when firing up the application.
|
*/

if (version_compare($app->version(), '8.0', '>=')) {
    fwrite(STDERR, 'Caching Laravel routes'.PHP_EOL);

    $console->call('route:cache');
}

/*
|--------------------------------------------------------------------------
| Start PHP-FPM
|--------------------------------------------------------------------------
|
| We need to boot the PHP-FPM process with the appropriate handler so it
| is ready to accept requests. This will initialize this process then
| wait for this socket to become ready before continuing execution.
|
*/

fwrite(STDERR, 'Preparing to boot FPM'.PHP_EOL);

$fpm = Fpm::boot(
    __DIR__.'/httpHandler.php', $secrets
);

/*
|--------------------------------------------------------------------------
| Listen For Lambda Invocations
|--------------------------------------------------------------------------
|
| When using FPM, we will listen for Lambda invocations and proxy them
| through the FPM process. We'll then return formatted FPM response
| back to the user. We'll monitor FPM to make sure it is running.
|
*/

$invocations = 0;

$lambdaRuntime = LambdaRuntime::fromEnvironmentVariable();

while (true) {
    $lambdaRuntime->nextInvocation(function ($invocationId, $event) {
        return FpmHttpHandlerFactory::make($event)
                    ->handle($event)
                    ->toApiGatewayFormat();
    });

    $fpm->ensureRunning();

    LambdaContainer::terminateIfInvocationLimitHasBeenReached(
        ++$invocations, (int) ($_ENV['VAPOR_MAX_REQUESTS'] ?? 250)
    );
}
