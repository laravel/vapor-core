<?php

namespace Laravel\Vapor\Runtime;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Response
{
    public static function statusText($status)
    {
        $statusTexts = SymfonyResponse::$statusTexts;

        $statusTexts[419] = 'Token expired';

        return $statusTexts[$status];
    }
}