<?php

namespace Laravel\Vapor\Runtime;

class Response
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
     * Create a new response instance.
     *
     * @param  string  $body
     * @param  array  $headers
     * @param  int  $status
     * @return void
     */
    public function __construct($body, $headers, $status)
    {
        $this->body = $body;
        $this->status = $status;

        $this->headers = $this->prepareHeaders($headers);
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
