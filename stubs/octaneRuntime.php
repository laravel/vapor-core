<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Laravel\Vapor\Runtime\LambdaContainer;
use Laravel\Vapor\Runtime\LambdaRuntime;
use Laravel\Vapor\Runtime\Octane\Octane;
use Laravel\Vapor\Runtime\Octane\OctaneHttpHandlerFactory;
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

fwrite(STDERR, 'Preparing to add secrets to runtime'.PHP_EOL);

$secrets = Secrets::addToEnvironment(
    $_ENV['VAPOR_SSM_PATH'],
    json_decode($_ENV['VAPOR_SSM_VARIABLES'] ?? '[]', true),
    __DIR__.'/vaporSecrets.php'
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

    fwrite(STDERR, 'Caching Laravel configuration'.PHP_EOL);

    $app->make(ConsoleKernelContract::class)->call('config:cache');
});

/*
|--------------------------------------------------------------------------
| Start Octane Worker
|--------------------------------------------------------------------------
|
| We need to boot the application request Octane worker so it's ready to
| serve incoming requests. This will initialize this worker then wait
| for Lambda invocations to be received for this Vapor application.
|
*/
fwrite(STDERR, 'Preparing to boot Octane'.PHP_EOL);

Octane::boot(
    __DIR__,
    getenv('OCTANE_DATABASE_SESSION_PERSIST') === 'true',
    getenv('OCTANE_DATABASE_SESSION_TTL') ?: 0
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
        return OctaneHttpHandlerFactory::make($event)
                    ->handle($event)
                    ->toApiGatewayFormat();
    });

    LambdaContainer::terminateIfInvocationLimitHasBeenReached(
        ++$invocations, (int) ($_ENV['VAPOR_MAX_REQUESTS'] ?? 250)
    );
}
