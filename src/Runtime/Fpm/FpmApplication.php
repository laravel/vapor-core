<?php

namespace Laravel\Vapor\Runtime\Fpm;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;

class FpmApplication
{
    /**
     * The socket client instance.
     *
     * @var \Hoa\Socket\Client
     */
    protected $client;

    /**
     * The FPM socket connection instance.
     *
     * @var \Hoa\FastCGI\SocketConnections\UnixDomainSocket
     */
    protected $socketConnection;

    /**
     * Create a new FPM application instance.
     *
     * @param  \hollodotme\FastCGI\Client  $client
     * @param  \Hoa\FastCGI\SocketConnections\UnixDomainSocket  $socketConnection
     * @return void
     */
    public function __construct(Client $client, UnixDomainSocket $socketConnection)
    {
        $this->client = $client;
        $this->socketConnection = $socketConnection;
    }

    /**
     * Handle the given FPM request.
     *
     * @param  \Laravel\Vapor\Runtime\Fpm\FpmRequest  $request
     * @return \Laravel\Vapor\Runtime\Fpm\FpmResponse
     */
    public function handle(FpmRequest $request)
    {
        return new FpmResponse(
            $this->client->sendRequest($this->socketConnection, $request)
        );
    }
}
