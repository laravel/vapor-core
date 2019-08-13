<?php

namespace Laravel\Vapor\Runtime\Fpm;

use hollodotme\FastCGI\Interfaces\ProvidesResponseData;

class FpmResponse
{
    /**
     * The response status code.
     *
     * @var int
     */
    public $status;

    /**
     * The response headers.
     *
     * @var array
     */
    public $headers;

    /**
     * The response body.
     *
     * @var string
     */
    public $body;

    /**
     * Create a new FPM response instance.
     *
     * @param  \hollodotme\FastCGI\Interfaces\ProvidesResponseData  $response
     * @return void
     */
    public function __construct(ProvidesResponseData $response)
    {
        $this->body = $response->getBody();

        $headers = FpmResponseHeaders::fromBody($response->getOutput());

        $this->status = $this->prepareStatus($headers);
        $this->headers = $this->prepareHeaders($headers);
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
                ? explode(' ', $headers['status'][0])[0]
                : 200;
    }

    /**
     * Prepare the given response headers.
     *
     * @param  array  $headers
     * @return array
     */
    protected function prepareHeaders(array $headers)
    {
        $headers = array_change_key_case($headers, CASE_LOWER);

        unset($headers['status']);

        return $headers;
    }
}
