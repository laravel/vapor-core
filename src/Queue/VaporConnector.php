<?php

namespace Laravel\Vapor\Queue;

use AsyncAws\Sqs\SqsClient;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Symfony\Component\HttpClient\HttpClient;

class VaporConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @param  array  $config
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        $clientConfig = [];
        if ($config['key'] && $config['secret']) {
            $clientConfig['accessKeyId'] = $config['key'] ?? null;
            $clientConfig['accessKeySecret'] = $config['secret'] ?? null;
            $clientConfig['sessionToken'] = $config['token'] ?? null;
        }

        return new VaporQueue(
            new SqsClient($clientConfig, null, HttpClient::create(['timeout'=>60])),
            $config['queue'],
            $config['prefix'] ?? ''
        );
    }
}
