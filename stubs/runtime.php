<?php

/*
|--------------------------------------------------------------------------
| Download the vendor directory
|--------------------------------------------------------------------------
|
| To be able to run the application, we are going to download the vendor
| archive from the s3 bucket and extract it. The vendor directory will
| be available for the application to use from now on.
|
*/

fwrite(STDERR, 'Downloading the vendor archive');

exec(
    sprintf('/opt/awscli/aws s3 presign s3://%s/%s-vendor.zip --expires-in 300',
        $_ENV['VAPOR_ARTIFACT_BUCKET_NAME'],
        $_ENV['VAPOR_ARTIFACT_NAME']
    ),
    $output
);

file_put_contents('/tmp/vendor.zip', fopen($output[0], 'r'));

$zip = new ZipArchive;

$zip->open('/tmp/vendor.zip');

$zip->extractTo('/tmp/vendor');

$zip->close();

unlink('/tmp/vendor.zip');

require '/tmp/vendor/autoload.php';

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
    // return require __DIR__.'/httpRuntime.php';
}
