<?php

use hollodotme\FastCGI\Exceptions\WriteFailedException;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Support\Str;
use Laravel\Vapor\Runtime\Environment;
use Laravel\Vapor\Runtime\Fpm\Fpm;
use Laravel\Vapor\Runtime\Fpm\FpmHttpHandlerFactory;
use Laravel\Vapor\Runtime\LambdaContainer;
use Laravel\Vapor\Runtime\LambdaResponse;
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

function_exists('__vapor_debug') && __vapor_debug('Preparing to add secrets to runtime');

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

function_exists('__vapor_debug') && __vapor_debug('Caching Laravel configuration');

$app->make(ConsoleKernelContract::class)->call('config:cache');

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

function_exists('__vapor_debug') && __vapor_debug('Preparing to boot FPM');

$fpm = Fpm::boot(
    __DIR__.'/httpHandler.php'
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
        try {
            return FpmHttpHandlerFactory::make($event)
                ->handle($event)
                ->toApiGatewayFormat();
        } catch (WriteFailedException $e) {
            if (Str::contains($e->getMessage(), 'Failed to write request to socket [broken pipe]')) {
                function_exists('__vapor_debug') && __vapor_debug($e->getMessage());

                return (new LambdaResponse(502, [], ''))
                    ->toApiGatewayFormat();
            }

            throw $e;
        }
    });

    $fpm->ensureRunning();

    LambdaContainer::terminateIfInvocationLimitHasBeenReached(
        ++$invocations, (int) ($_ENV['VAPOR_MAX_REQUESTS'] ?? 250)
    );
}
