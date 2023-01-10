<?php

namespace Laravel\Vapor\Logging;

use Monolog\Formatter\JsonFormatter as BaseJsonFormatter;
use Monolog\LogRecord;

class JsonFormatter extends BaseJsonFormatter
{
    /**
     * {@inheritdoc}
     */
    public function format($record): string
    {
        $context = ['aws_request_id' => ($_ENV['AWS_REQUEST_ID'] ?? null)];

        if ($record instanceof LogRecord) {
            $record = new LogRecord(
                $record->datetime,
                $record->channel,
                $record->level,
                $record->message,
                array_merge($record->context, $context),
                $record->extra,
                $record->formatted
            );
        } else {
            $record['context'] = array_merge(
                $record['context'] ?? [], $context
            );
        }

        return parent::format($record);
    }
}
