<?php

namespace Laravel\Vapor\Runtime;

use Aws\Ssm\SsmClient;

class Secrets
{
    /**
     * Add all of the secret parameters at the given path to the environment.
     *
     * @param  string  $path
     * @param  array|null  $parameters
     * @param  string  $file
     * @return array
     */
    public static function addToEnvironment($path, $parameters, $file)
    {
        if (! $parameters && file_exists($file)) {
            $parameters = require $file;
        }

        return tap(static::all($path, (array) $parameters), function ($variables) {
            foreach ($variables as $key => $value) {
                echo "Injecting secret [{$key}] into runtime.\r";

                $_ENV[$key] = $value;
            }
        });
    }

    /**
     * Get all of the secret parameters (AWS SSM) at the given path.
     *
     * @param  string  $path
     * @param  array  $parameters
     * @return array
     */
    public static function all($path, array $parameters = [])
    {
        if (empty($parameters)) {
            return [];
        }

        $ssm = SsmClient::factory([
            'region' => $_ENV['AWS_DEFAULT_REGION'],
            'version' => 'latest',
        ]);

        return collect($parameters)->chunk(10)->reduce(function ($carry, $parameters) use ($ssm, $path) {
            $ssmResponse = $ssm->getParameters([
                'Names' => collect($parameters)->map(function ($version, $parameter) use ($path) {
                    return $path.'/'.$parameter.':'.$version;
                })->values()->all(),
                'WithDecryption' => true,
            ]);

            return array_merge($carry, static::parseSecrets(
                $ssmResponse['Parameters'] ?? []
            ));
        }, []);
    }

    /**
     * Parse the secret names and values into an array.
     *
     * @param  array  $secrets
     * @return array
     */
    protected static function parseSecrets(array $secrets)
    {
        return collect($secrets)->mapWithKeys(function ($secret) {
            $segments = explode('/', $secret['Name']);

            return [$segments[count($segments) - 1] => $secret['Value']];
        })->all();
    }
}
