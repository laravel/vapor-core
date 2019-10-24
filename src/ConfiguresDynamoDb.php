<?php

namespace Laravel\Vapor;

use Illuminate\Support\Facades\Config;

trait ConfiguresDynamoDb
{
    /**
     * Ensure DynamoDb is properly configured.
     *
     * @return void
     */
    protected function ensureDynamoDbIsConfigured()
    {
        // Ensure we are running on Vapor...
        if (! isset($_ENV['VAPOR_SSM_PATH'])) {
            return;
        }

        Config::set('cache.stores.dynamodb', array_merge([
            'driver' => 'dynamodb',
            'key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? null,
            'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null,
            'region' => $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1',
            'table' => $_ENV['DYNAMODB_CACHE_TABLE'] ?? 'cache',
            'endpoint' => $_ENV['DYNAMODB_ENDPOINT'] ?? null,
        ], Config::get('cache.stores.dynamodb') ?? []));
    }
}
