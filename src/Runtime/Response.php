<?php

namespace Laravel\Vapor\Runtime;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Response
{
    /**
     * Get the the status text for the given status code.
     *
     * @param  int  $status
     * @return string
     */
    public static function statusText($status)
    {
        $statusTexts = SymfonyResponse::$statusTexts;

        $statusTexts[419] = 'Authentication Timeout';

        return $statusTexts[$status];
    }
}
