<?php

if (! function_exists('__vapor_debug')) {
    function __vapor_debug($message)
    {
        if (isset($_ENV['VAPOR_DEBUG']) && $_ENV['VAPOR_DEBUG'] === 'true') {
            fwrite(STDERR, $message.PHP_EOL);
        }
    }
}
