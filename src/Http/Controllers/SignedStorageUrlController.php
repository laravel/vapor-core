<?php

namespace Laravel\Vapor\Http\Controllers;

use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Vapor\Contracts\SignedStorageUrlController as SignedStorageUrlControllerContract;

class SignedStorageUrlController extends Controller implements SignedStorageUrlControllerContract
{
    /**
     * Create a new signed URL.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->ensureEnvironmentVariablesAreAvailable($request);

        Gate::authorize('uploadFiles', [
            $request->user(),
            $bucket = $request->input('bucket') ?: $_ENV['AWS_BUCKET']
        ]);

        $client = $this->storageClient();

        $uuid = (string) Str::uuid();

        $url = $client->presign(
            $this->createCommand($request, $client, $bucket, $key = ('tmp/'.$uuid)),
            new \DateTimeImmutable('+5 minutes')
        );

        return response()->json([
            'uuid' => $uuid,
            'bucket' => $bucket,
            'key' => $key,
            'url' => $url,
            'headers' => ['Content-Type' => $request->input('content_type') ?: 'application/octet-stream']
        ], 201);
    }

    /**
     * Create the input for the PUT operation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $bucket
     * @param  string  $key
     * @return \AsyncAws\S3\Input\PutObjectRequest
     */
    protected function createCommand(Request $request, $bucket, $key)
    {
        return new PutObjectRequest(array_filter([
            'Bucket' => $bucket,
            'Key' => $key,
            'ACL' => $request->input('visibility') ?: $this->defaultVisibility(),
            'ContentType' => $request->input('content_type') ?: 'application/octet-stream',
            'CacheControl' => $request->input('cache_control') ?: null,
            'Expires' => $request->input('expires') ?: null,
        ]));
    }

    /**
     * Get the headers that should be used when making the signed request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \GuzzleHttp\Psr7\Request
     * @return array
     */
    protected function headers(Request $request, $signedRequest)
    {
        return array_merge(
            $signedRequest->getHeaders(),
            [
                'Content-Type' => $request->input('content_type') ?: 'application/octet-stream'
            ]
        );
    }

    /**
     * Ensure the required environment variables are available.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function ensureEnvironmentVariablesAreAvailable(Request $request)
    {
        $missing = array_diff_key(array_flip(array_filter([
            $request->input('bucket') ? null : 'AWS_BUCKET',
            'AWS_DEFAULT_REGION',
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY'
        ])), $_ENV);

        if (empty($missing)) {
            return;
        }

        throw new InvalidArgumentException(
            "Unable to issue signed URL. Missing environment variables: ".implode(', ', array_keys($missing))
        );
    }

    /**
     * Get the S3 storage client instance.
     *
     * @return \AsyncAws\S3\S3Client
     */
    protected function storageClient()
    {
        $config = [
            'region' => $_ENV['AWS_DEFAULT_REGION'],
        ];

        if (! isset($_ENV['AWS_LAMBDA_FUNCTION_VERSION'])) {
            $config['credentials'] = array_filter([
                'accessKeyId' => $_ENV['AWS_ACCESS_KEY_ID'] ?? null,
                'accessKeySecret' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null,
                'sessionToken' => $_ENV['AWS_SESSION_TOKEN'] ?? null,
            ]);
        }

        return new S3Client($config);
    }

    /**
     * Get the default visibility for uploads.
     *
     * @return string
     */
    protected function defaultVisibility()
    {
        return 'private';
    }
}
