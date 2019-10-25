<?php

namespace Laravel\Vapor\Logging;

use Monolog\Formatter\JsonFormatter as BaseJsonFormatter;

class JsonFormatter extends BaseJsonFormatter
{
    /**
     * {@inheritdoc}
     */
    public function format(array $record) : string
    {
        $record['context'] = array_merge(
            $record['context'] ?? [], ['aws_request_id' => ($_ENV['AWS_REQUEST_ID'] ?? null)]
        );

        return parent::format($record);
    }
}
