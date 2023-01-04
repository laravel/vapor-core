<?php

namespace Laravel\Vapor\Runtime\Octane;

use Illuminate\Support\Arr as SupportArr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Octane\RequestContext;
use Laravel\Vapor\Arr;
use Laravel\Vapor\Runtime\Request;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\UploadedFile;
use Riverline\MultiPartParser\Part;

class OctaneRequestContextFactory
{
    /**
     * Creates an Octane request context from the given event.
     *
     * @param  array  $event
     * @param  array  $serverVariables
     * @return \Laravel\Octane\RequestContext
     */
    public static function fromEvent($event, $serverVariables)
    {
        $request = Request::fromLambdaEvent($event, $serverVariables);

        $method = $request->serverVariables['REQUEST_METHOD'];

        $contentType = array_change_key_case($request->headers)['content-type'] ?? null;

        $serverRequest = new ServerRequest(
            $request->serverVariables['REQUEST_METHOD'],
            static::parseUri($request->serverVariables['REQUEST_URI']),
            $request->headers,
            $request->body,
            $request->serverVariables['SERVER_PROTOCOL'],
            $request->serverVariables
        );

        $serverRequest = $serverRequest->withCookieParams(static::cookies($request->headers));

        $serverRequest = $serverRequest->withUploadedFiles(static::uploadedFiles(
            $method, $contentType, $request->body
        ));

        $serverRequest = $serverRequest->withParsedBody(static::parsedBody(
            $method, $contentType, $request->body
        ));

        parse_str($request->serverVariables['QUERY_STRING'], $queryParams);

        $serverRequest = $serverRequest->withQueryParams($queryParams);

        return new RequestContext([
            'psr7Request' => $serverRequest,
        ]);
    }

    /**
     * Get the cookies from the given headers.
     *
     * @param  array  $headers
     * @return array
     */
    protected static function cookies($headers)
    {
        $headers = array_change_key_case($headers);

        if (! isset($headers['cookie']) || empty($headers['cookie'])) {
            return [];
        }

        return Collection::make(explode('; ', $headers['cookie']))->mapWithKeys(function ($cookie) {
            $cookie = explode('=', trim($cookie), 2);

            $key = $cookie[0];

            if (! isset($cookie[1])) {
                return [$key => null];
            }

            return [$key => urldecode($cookie[1])];
        })->filter()->all();
    }

    /**
     * Create a new file instance from the given HTTP request document part.
     *
     * @param  \Riverline\MultipartParser\Part  $part
     * @return \Psr\Http\Message\UploadedFileInterface
     */
    protected static function createFile($part)
    {
        file_put_contents(
            $path = tempnam(sys_get_temp_dir(), 'vapor_upload_'),
            $part->getBody()
        );

        return new UploadedFile(
            $path,
            filesize($path),
            UPLOAD_ERR_OK,
            $part->getFileName(),
            $part->getMimeType()
        );
    }

    /**
     * Parse the files for the given HTTP request body.
     *
     * @param  string  $contentType
     * @param  string  $body
     * @return array
     */
    protected static function parseFiles($contentType, $body)
    {
        $document = new Part("Content-Type: $contentType\r\n\r\n".$body);

        if (! $document->isMultiPart()) {
            return [];
        }

        return Collection::make($document->getParts())
            ->filter
            ->isFile()
            ->reduce(function ($files, $part) {
                return Str::contains($name = $part->getName(), '[')
                    ? Arr::setMultiPartArrayValue($files, $name, static::createFile($part))
                    : SupportArr::set($files, $name, static::createFile($part));
            }, []);
    }

    /**
     * Get the uploaded files for the incoming event.
     *
     * @param  string  $method
     * @param  string  $contentType
     * @param  string  $body
     * @return array
     */
    protected static function uploadedFiles($method, $contentType, $body)
    {
        if (! in_array($method, ['POST', 'PUT']) ||
            is_null($contentType) ||
            static::isUrlEncodedForm($contentType)) {
            return [];
        }

        return static::parseFiles($contentType, $body);
    }

    /**
     * Get the parsed body for the event.
     *
     * @param  string  $method
     * @param  string  $contentType
     * @param  string  $body
     * @return array|null
     */
    protected static function parsedBody($method, $contentType, $body)
    {
        if (! in_array($method, ['POST', 'PUT']) || is_null($contentType)) {
            return;
        }

        if (static::isUrlEncodedForm($contentType)) {
            parse_str($body, $parsedBody);

            return $parsedBody;
        }

        return static::parseBody($contentType, $body);
    }

    /**
     * Parse the incoming event's request body.
     *
     * @param  string  $contentType
     * @param  string  $body
     * @return array
     */
    protected static function parseBody($contentType, $body)
    {
        $document = new Part("Content-Type: $contentType\r\n\r\n".$body);

        if (! $document->isMultiPart()) {
            return;
        }

        return Collection::make($document->getParts())
            ->reject
            ->isFile()
            ->reduce(function ($parsedBody, $part) {
                return Str::contains($name = $part->getName(), '[')
                    ? Arr::setMultiPartArrayValue($parsedBody, $name, $part->getBody())
                    : SupportArr::set($parsedBody, $name, $part->getBody());
            }, []);
    }

    /**
     * Parse the incoming event's request uri.
     *
     * @param  string  $uri
     * @return string
     */
    protected static function parseUri($uri)
    {
        if (parse_url($uri) === false) {
            return '/';
        }

        return $uri;
    }

    /**
     * Determine if the given content type represents a URL encoded form.
     *
     * @param  string  $contentType
     * @return bool
     */
    protected static function isUrlEncodedForm($contentType)
    {
        return Str::contains(strtolower($contentType), 'application/x-www-form-urlencoded');
    }
}
