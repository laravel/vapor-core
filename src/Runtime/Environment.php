<?php

namespace Laravel\Vapor\Runtime;

use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class Environment
{
    /**
     * Decrypt and encrypted environment file into the runtime.
     *
     * @return void
     */
    public static function decrypt()
    {
        if (static::cannotBeDecrypted()) {
            return;
        }

        $basePath = app()->basePath();

        File::copy(static::encryptedFilePath(), '/tmp/'.static::encryptedFile());

        app()->setBasePath('/tmp');

        app()->make(ConsoleKernelContract::class)->call('env:decrypt', ['--env' => static::environment()]);

        Dotenv::createImmutable(app()->basePath(), static::environmentFile())->load();

        app()->setBasePath($basePath);
    }

    /**
     * Determine if it is possible to decrypt an environment file.
     *
     * @return bool
     */
    public static function canBeDecrypted()
    {
        if (! isset($_ENV['LARAVEL_ENV_ENCRYPTION_KEY'])) {
            fwrite(STDERR, 'No decryption key set.'.PHP_EOL);

            return false;
        }

        if (! in_array('env:decrypt', array_keys(Artisan::all()))) {
            fwrite(STDERR, 'Decrypt command not available.'.PHP_EOL);

            return false;
        }

        if (! File::exists(app()->basePath(static::encryptedFile()))) {
            fwrite(STDERR, 'Encrypted environment file not found.'.PHP_EOL);

            return false;
        }

        return true;
    }

    /**
     * Determine is it is not possible to decrypt an environment.
     *
     * @return bool
     */
    public static function cannotBeDecrypted()
    {
        return ! static::canBeDecrypted();
    }

    /**
     * Returns the current environment.
     *
     * @return string
     */
    public static function environment()
    {
        return isset($_ENV['APP_ENV']) ? $_ENV['APP_ENV'] : 'production';
    }

    /**
     * Returns the environment file name for the current environment.
     *
     * @return string
     */
    public static function environmentFile()
    {
        return '.env.'.static::environment();
    }

    /**
     * Returns the encrypted file name for the current environment.
     *
     * @return string
     */
    public static function encryptedFile()
    {
        return static::environmentFile().'.encrypted';
    }

    /**
     * Returns the full path to the encrypted file for the current environment.
     *
     * @return string
     */
    public static function encryptedFilePath()
    {
        return app()->basePath(static::encryptedFile());
    }
}
