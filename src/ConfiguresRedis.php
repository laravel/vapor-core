<?php

namespace Laravel\Vapor;

use Illuminate\Support\Facades\Config;

trait ConfiguresRedis
{
    /**
     * Ensure Redis is properly configured.
     *
     * @return void
     */
    protected function ensureRedisIsConfigured()
    {
        if (! isset($_ENV['VAPOR_CACHE']) || $_ENV['VAPOR_CACHE'] !== 'true') {
            return;
        }

        Config::set('database.redis', [
            'client' => $_ENV['REDIS_CLIENT'] ?? 'phpredis',
            'options' => array_merge([
                'cluster' => $_ENV['REDIS_CLUSTER'] ?? 'redis',
            ], Config::get('database.redis.options', [])),
            'clusters' => array_merge([
                'default' => [
                    [
                        'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
                        'password' => null,
                        'port' => 6379,
                        'database' => 0,
                    ],
                ],
                'cache' => [
                    [
                        'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
                        'password' => null,
                        'port' => 6379,
                        'database' => 0,
                    ],
                ],
            ], Config::get('database.redis.clusters', [])),
        ]);
    }
}
