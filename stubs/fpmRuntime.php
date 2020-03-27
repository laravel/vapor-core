<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Laravel\Vapor\Runtime\Fpm\Fpm;
use Laravel\Vapor\Runtime\HttpHandlerFactory;
use Laravel\Vapor\Runtime\LambdaContainer;
use Laravel\Vapor\Runtime\LambdaRuntime;
use Laravel\Vapor\Runtime\Secrets;
use Laravel\Vapor\Runtime\StorageDirectories;

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

fwrite(STDERR, 'Preparing to add secrets to runtime');

$secrets = Secrets::addToEnvironment(
    $_ENV['VAPOR_SSM_PATH'],
    json_decode($_ENV['VAPOR_SSM_VARIABLES'] ?? '[]', true),
    __DIR__.'/vaporSecrets.php'
);

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

fwrite(STDERR, 'Preparing to boot FPM');

$fpm = Fpm::boot(
    __DIR__.'/httpHandler.php', $secrets
);

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

with(require __DIR__.'/bootstrap/app.php', function ($app) {
    StorageDirectories::create();

    $app->useStoragePath(StorageDirectories::PATH);

    fwrite(STDERR, 'Caching Laravel configuration');

    $app->make(ConsoleKernelContract::class)->call('config:cache');
});

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
    $lambdaRuntime->nextInvocation(function ($invocationId, $event) use ($fpm, $invocations) {
        return HttpHandlerFactory::make($event)
                    ->handle($event)
                    ->toApiGatewayFormat();
    });

    $fpm->ensureRunning();

    LambdaContainer::terminateIfInvocationLimitHasBeenReached(
        ++$invocations, (int) ($_ENV['VAPOR_MAX_REQUESTS'] ?? 250)
    );
}
