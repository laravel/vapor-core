<?php

namespace Laravel\Vapor\Support;

use InvalidArgumentException;
use LogicException;

/**
 * @see https://github.com/Riverline/multipart-parser/pull/41
 */
class Part
{
    /**
     * @var resource
     */
    protected $stream;

    /**
     * @var array
     */
    protected $headers;

    /**
     * @var int
     */
    protected $bodyOffset;

    /**
     * @var array
     */
    protected $parts = [];

    /**
     * The length of the EOL character.
     *
     * @var int
     */
    protected $EOLCharacterLength;

    /**
     * @param  string|resource  $stream
     * @param  int  $EOLCharacterLength
     * @return void
     */
    public function __construct($content, $EOLCharacterLength = 2)
    {
        if (false === is_integer($EOLCharacterLength)) {
            throw new InvalidArgumentException('EOL Length is not an integer');
        }

        if (! is_resource($content)) {
            $stream = fopen('php://temp', 'rw');
            fwrite($stream, $content);
            rewind($stream);
        } else {
            $stream = $content;
        }

        $this->stream = $stream;
        $this->EOLCharacterLength = $EOLCharacterLength;

        rewind($this->stream);

        $endOfHeaders = false;
        $bufferSize = 8192;
        $headerLines = [];
        $buffer = '';

        while (false !== ($line = fgets($this->stream, $bufferSize))) {
            $buffer .= rtrim($line, "\r\n");

            if (strlen($line) === $bufferSize - 1) {
                continue;
            }

            if ('' === $buffer) {
                $endOfHeaders = true;
                break;
            }

            $trimmed = ltrim($buffer);
            if (strlen($buffer) > strlen($trimmed)) {
                $headerLines[count($headerLines) - 1] .= "\x20".$trimmed;
            } else {
                $headerLines[] = $buffer;
            }

            $buffer = '';
        }

        if (false === $endOfHeaders) {
            throw new InvalidArgumentException('Content is not valid');
        }

        $this->headers = [];
        foreach ($headerLines as $line) {
            $lineSplit = explode(':', $line, 2);

            if (2 === count($lineSplit)) {
                [$key, $value] = $lineSplit;

                $value = mb_decode_mimeheader(trim($value));
            } else {
                $key = $lineSplit[0];
                $value = '';
            }

            $key = strtolower($key);
            if (false === key_exists($key, $this->headers)) {
                $this->headers[$key] = $value;
            } else {
                if (false === is_array($this->headers[$key])) {
                    $this->headers[$key] = (array) $this->headers[$key];
                }
                $this->headers[$key][] = $value;
            }
        }

        $this->bodyOffset = ftell($stream);

        if ($this->isMultiPart()) {
            $boundary = self::getHeaderOption($this->getHeader('Content-Type'), 'boundary');

            if (null === $boundary) {
                throw new InvalidArgumentException("Can't find boundary in content type");
            }

            $separator = '--'.$boundary;

            $partOffset = 0;
            $endOfBody = false;
            while ($line = fgets($this->stream, $bufferSize)) {
                $trimmed = rtrim($line, "\r\n");

                if ($trimmed === $separator || $trimmed === $separator.'--') {
                    if ($partOffset > 0) {
                        $currentOffset = ftell($this->stream);
                        $eofLength = strlen($line) - strlen($trimmed);
                        $partLength = $currentOffset - $partOffset - strlen($trimmed) - (2 * $eofLength);

                        if ($eofLength === 0 && feof($this->stream)) {
                            $partLength = $currentOffset - $partOffset - strlen($line) - $this->EOLCharacterLength;
                        }

                        $partStream = fopen('php://temp', 'rw');
                        stream_copy_to_stream($this->stream, $partStream, $partLength, $partOffset);
                        $this->parts[] = new static($partStream, $this->EOLCharacterLength);
                        fseek($this->stream, $currentOffset);
                    }

                    if ($trimmed === $separator.'--') {
                        $endOfBody = true;
                        break;
                    }

                    $partOffset = ftell($this->stream);
                }
            }

            if (0 === count($this->parts) || false === $endOfBody
            ) {
                throw new LogicException("Can't find multi-part content");
            }
        }
    }

    /**
     * @return bool
     */
    public function isMultiPart()
    {
        return 'multipart' === mb_strstr(
            self::getHeaderValue($this->getHeader('Content-Type')),
            '/',
            true
        );
    }

    /**
     * @return string
     *
     * @throws \LogicException if is multipart
     */
    public function getBody()
    {
        if ($this->isMultiPart()) {
            throw new LogicException("MultiPart content, there aren't body");
        }

        $body = stream_get_contents($this->stream, -1, $this->bodyOffset);

        $encoding = strtolower((string) $this->getHeader('Content-Transfer-Encoding'));
        switch ($encoding) {
            case 'base64':
                $body = base64_decode($body);
                break;
            case 'quoted-printable':
                $body = quoted_printable_decode($body);
                break;
        }

        if (false === in_array($encoding, ['binary', '7bit'])) {
            $contentType = $this->getHeader('Content-Type');
            $charset = self::getHeaderOption($contentType, 'charset');
            if (null === $charset) {
                $charset = mb_detect_encoding($body) ?: 'utf-8';
            }

            if ('utf-8' !== strtolower($charset)) {
                $body = mb_convert_encoding($body, 'utf-8', $charset);
            }
        }

        return $body;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function getHeader($key, $default = null)
    {
        $key = strtolower($key);

        if (false === isset($this->headers[$key])) {
            return $default;
        }

        return $this->headers[$key];
    }

    /**
     * @param  string  $header
     * @return string
     */
    public static function getHeaderValue($header)
    {
        [$value] = self::parseHeaderContent($header);

        return $value;
    }

    /**
     * @param  string  $header
     * @return array
     */
    public static function getHeaderOptions($header)
    {
        [, $options] = self::parseHeaderContent($header);

        return $options;
    }

    /**
     * @param  string  $header
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public static function getHeaderOption($header, $key, $default = null)
    {
        $options = self::getHeaderOptions($header);

        if (false === isset($options[$key])) {
            return $default;
        }

        return $options[$key];
    }

    /**
     * @return string
     */
    public function getMimeType()
    {
        $contentType = $this->getHeader('Content-Type');

        return self::getHeaderValue($contentType) ?: 'application/octet-stream';
    }

    /**
     * @return string|null
     */
    public function getName()
    {
        $contentDisposition = $this->getHeader('Content-Disposition');

        return self::getHeaderOption($contentDisposition, 'name');
    }

    /**
     * @return string|null
     */
    public function getFileName()
    {
        // Find Content-Disposition
        $contentDisposition = $this->getHeader('Content-Disposition');

        return self::getHeaderOption($contentDisposition, 'filename');
    }

    /**
     * Checks if the part is a file.
     *
     * @return bool
     */
    public function isFile()
    {
        return false === is_null($this->getFileName());
    }

    /**
     * @return array
     *
     * @throws \LogicException if is not multipart
     */
    public function getParts()
    {
        if (false === $this->isMultiPart()) {
            throw new LogicException("Not MultiPart content, there aren't any parts");
        }

        return $this->parts;
    }

    /**
     * @param  string  $name
     * @return array
     *
     * @throws \LogicException if is not multipart
     */
    public function getPartsByName($name)
    {
        $parts = [];

        foreach ($this->getParts() as $part) {
            if ($part->getName() === $name) {
                $parts[] = $part;
            }
        }

        return $parts;
    }

    /**
     * @param  string  $content
     * @return array
     */
    protected static function parseHeaderContent($content)
    {
        $parts = explode(';', (string) $content);
        $headerValue = array_shift($parts);
        $options = [];

        foreach ($parts as $part) {
            if (false === empty($part)) {
                $partSplit = explode('=', $part, 2);
                if (2 === count($partSplit)) {
                    [$key, $value] = $partSplit;
                    if ('*' === substr($key, -1)) {
                        $key = substr($key, 0, -1);
                        if (preg_match(
                            "/(?P<charset>[\w!#$%&+^_`{}~-]+)'(?P<language>[\w-]*)'(?P<value>.*)$/",
                            $value,
                            $matches
                        )) {
                            $value = mb_convert_encoding(
                                rawurldecode($matches['value']),
                                'utf-8',
                                $matches['charset']
                            );
                        }
                    }
                    $options[trim($key)] = trim($value, ' "');
                } else {
                    $options[$partSplit[0]] = '';
                }
            }
        }

        return [$headerValue, $options];
    }
}
