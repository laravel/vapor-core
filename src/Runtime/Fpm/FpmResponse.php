<?php

namespace Laravel\Vapor\Runtime\Fpm;

use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use Laravel\Vapor\Runtime\Response;

class FpmResponse extends Response
{
    /**
     * Create a new FPM response instance.
     *
     * @param  \hollodotme\FastCGI\Interfaces\ProvidesResponseData  $response
     * @return void
     */
    public function __construct(ProvidesResponseData $response)
    {
        $headers = FpmResponseHeaders::fromBody($response->getOutput());

        parent::__construct(
            $response->getBody(),
            $headers,
            $this->prepareStatus($headers)
        );
    }

    /**
     * Prepare the status code of the response.
     *
     * @return int
     */
    protected function prepareStatus(array $headers)
    {
        $headers = array_change_key_case($headers, CASE_LOWER);

        return isset($headers['status'][0])
            ? (int) explode(' ', $headers['status'][0])[0]
            : 200;
    }
}
