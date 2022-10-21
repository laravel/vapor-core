<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Laravel\Vapor\Runtime\CliHandlerFactory;
use Laravel\Vapor\Runtime\Environment;
use Laravel\Vapor\Runtime\LambdaContainer;
use Laravel\Vapor\Runtime\LambdaRuntime;
use Laravel\Vapor\Runtime\Secrets;
use Laravel\Vapor\Runtime\StorageDirectories;

$app = require __DIR__.'/bootstrap/app.php';

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

Secrets::addToEnvironment(
    $_ENV['VAPOR_SSM_PATH'],
    json_decode($_ENV['VAPOR_SSM_VARIABLES'] ?? '[]', true),
    __DIR__.'/vaporSecrets.php'
);

/*
|--------------------------------------------------------------------------
| Inject Decrypted Environment Variables
|--------------------------------------------------------------------------
|
| Next, we will check to see whether a decryption key has been set on the
| environment. If so, we will attempt to discover an encrypted file at
| the root of the application and decrypt it into the Vapor runtime.
|
*/

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

if (isset($_ENV['VAPOR_MAINTENANCE_MODE']) &&
    $_ENV['VAPOR_MAINTENANCE_MODE'] === 'true') {
    file_put_contents($app->storagePath().'/framework/down', '[]');
}

echo 'Caching Laravel configuration'.PHP_EOL;

try {
    $app->make(ConsoleKernelContract::class)->call('config:cache');
} catch (Throwable $e) {
    echo 'Failing caching Laravel configuration: '.$e->getMessage().PHP_EOL;
}

/*
|--------------------------------------------------------------------------
| Listen For Lambda Invocations
|--------------------------------------------------------------------------
|
| When receiving Lambda requests to the CLI environment, we simply send
| them to the appropriate handlers based on if they are CLI commands
| or queued jobs. Then we'll return a response back to the Lambda.
|
*/

$invocations = 0;

$lambdaRuntime = LambdaRuntime::fromEnvironmentVariable();

while (true) {
    $lambdaRuntime->nextInvocation(function ($invocationId, $event) {
        return CliHandlerFactory::make($event)
                        ->handle($event)
                        ->toApiGatewayFormat();
    });

    LambdaContainer::terminateIfInvocationLimitHasBeenReached(
        ++$invocations, (int) ($_ENV['VAPOR_MAX_REQUESTS'] ?? 250)
    );
}
