<?php

namespace Laravel\Vapor\Runtime\Fpm;

use hollodotme\FastCGI\Interfaces\ProvidesRequestData;
use Laravel\Vapor\Runtime\Request;

class FpmRequest extends Request implements ProvidesRequestData
{
    use ActsAsFastCgiDataProvider;
}
