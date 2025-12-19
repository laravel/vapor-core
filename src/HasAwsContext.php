<?php

namespace Laravel\Vapor;

trait HasAwsContext
{
    /**
     * Get the current AWS request ID.
     *
     * @return string|null The AWS request ID if available.
     */
    public static function requestId(): ?string
    {
        return $_ENV['AWS_REQUEST_ID'] ?? null;
    }

    /**
     * Get the AWS Lambda function name.
     *
     * @return string|null The Lambda function name if available.
     */
    public static function functionName(): ?string
    {
        return $_ENV['AWS_LAMBDA_FUNCTION_NAME'] ?? null;
    }

    /**
     * Get the AWS Lambda function version.
     *
     * @return string|null The Lambda function version if available.
     */
    public static function functionVersion(): ?string
    {
        return $_ENV['AWS_LAMBDA_FUNCTION_VERSION'] ?? null;
    }

    /**
     * Get the AWS CloudWatch log group name.
     *
     * @return string|null The CloudWatch log group name if available.
     */
    public static function logGroupName(): ?string
    {
        return $_ENV['AWS_LAMBDA_LOG_GROUP_NAME'] ?? null;
    }

    /**
     * Get the AWS CloudWatch log stream name.
     *
     * @return string|null The CloudWatch log stream name if available.
     */
    public static function logStreamName(): ?string
    {
        return $_ENV['AWS_LAMBDA_LOG_STREAM_NAME'] ?? null;
    }

    /**
     * Get the AWS execution context for the current Vapor environment.
     *
     * @return array{
     *     request_id: string|null,
     *     function_name: string|null,
     *     function_version: string|null,
     *     log_group_name: string|null,
     *     log_stream_name: string|null
     * }
     */
    protected function awsContext(): array
    {
        return [
            'request_id' => self::requestId(),
            'function_name' => self::functionName(),
            'function_version' => self::functionVersion(),
            'log_group_name' => self::logGroupName(),
            'log_stream_name' => self::logStreamName(),
        ];
    }
}
