<?php

namespace Laravel\Vapor\Runtime\Fpm;

use hollodotme\FastCGI\Client;

class FpmApplication
{
    /**
     * The socket client instance.
     *
     * @var \Hoa\Socket\Client
     */
    protected $client;

    /**
     * Create a new FPM application instance.
     *
     * @param  \hollodotme\FastCGI\Client  $client
     * @return void
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Handle the given FPM request.
     *
     * @param  \Laravel\Vapor\Runtime\Fpm\FpmRequest  $request
     * @return \Laravel\Vapor\Runtime\Fpm\FpmResponse
     */
    public function handle(FpmRequest $request)
    {
        return new FpmResponse($this->client->sendRequest($request));
    }
}
