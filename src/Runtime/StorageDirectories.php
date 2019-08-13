<?php

namespace Laravel\Vapor\Runtime;

class StorageDirectories
{
    /**
     * The storage path for the execution environment.
     *
     * @var string
     */
    public const PATH = '/tmp/storage';

    /**
     * Ensure the necessary storage directories exist.
     *
     * @return void
     */
    public static function create()
    {
        $directories = [
            self::PATH.'/app',
            self::PATH.'/bootstrap/cache',
            self::PATH.'/framework/cache',
            self::PATH.'/framework/views',
        ];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                echo "Creating storage directory: $directory.\r";

                mkdir($directory, 0755, true);
            }
        }
    }
}
