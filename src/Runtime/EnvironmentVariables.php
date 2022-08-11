<?php

namespace Laravel\Vapor\Runtime;

class EnvironmentVariables
{
    /**
     * Loads environment variables from the given file.
     *
     * @param  string  $file
     * @return array
     */
    public static function addToEnvironment($file)
    {
        return collect(file_exists($file) ? require $file : [])
            ->filter(function ($value, $key) {
                return ! isset($_ENV[$key]);
            })->each(function ($value, $key) {
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            })->toArray();
    }
}
