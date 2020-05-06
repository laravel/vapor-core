<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Redirect robots.txt
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Vapor will redirect requests to the
    | robots.txt file to the location in your assets S# bucket or
    | Cloudfront distribution.
    |
    */

    'redirect_robots_txt' => true,

    /*
    |--------------------------------------------------------------------------
    | Redirect www to the root domain
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Vapor will redirect requests to the
    | www subdomain to the root domain. If the option is disabled,
    | vapor will redirect the root domain to the www subdomain.
    |
    */

    'redirect_www' => true,
];
