<?php

if (! function_exists('__vapor_debug')) {
    function __vapor_debug($message)
    {
        fwrite(STDERR, $message.PHP_EOL);
    }
}
