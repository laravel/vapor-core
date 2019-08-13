<?php

namespace Laravel\Vapor\Runtime\Fpm;

trait ActsAsFastCgiDataProvider
{
    /**
     * {@inheritdoc}
     */
    public function getGatewayInterface() : string
    {
        return 'FastCGI/1.0';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestMethod() : string
    {
        return $this->serverVariables['REQUEST_METHOD'];
    }

    /**
     * {@inheritdoc}
     */
    public function getScriptFilename() : string
    {
        return $this->serverVariables['SCRIPT_FILENAME'];
    }

    /**
     * {@inheritdoc}
     */
    public function getServerSoftware() : string
    {
        return 'vapor';
    }

    /**
     * {@inheritdoc}
     */
    public function getRemoteAddress() : string
    {
        return $this->serverVariables['REMOTE_ADDR'];
    }

    /**
     * {@inheritdoc}
     */
    public function getRemotePort() : int
    {
        return $this->serverVariables['SERVER_PORT'];
    }

    /**
     * {@inheritdoc}
     */
    public function getServerAddress() : string
    {
        return $this->serverVariables['SERVER_ADDR'];
    }

    /**
     * {@inheritdoc}
     */
    public function getServerPort() : int
    {
        return $this->serverVariables['SERVER_PORT'];
    }

    /**
     * {@inheritdoc}
     */
    public function getServerName() : string
    {
        return $this->serverVariables['SERVER_NAME'];
    }

    /**
     * {@inheritdoc}
     */
    public function getServerProtocol() : string
    {
        return $this->serverVariables['SERVER_PROTOCOL'];
    }

    /**
     * {@inheritdoc}
     */
    public function getContentType() : string
    {
        return $this->serverVariables['CONTENT_TYPE'];
    }

    /**
     * {@inheritdoc}
     */
    public function getContentLength() : int
    {
        return $this->serverVariables['CONTENT_LENGTH'] ?: 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getContent() : string
    {
        return $this->body;
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomVars() : array
    {
        return $this->serverVariables;
    }

    /**
     * {@inheritdoc}
     */
    public function getParams() : array
    {
        return $this->serverVariables;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestUri() : string
    {
        return $this->serverVariables['PATH_INFO'];
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseCallbacks() : array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getFailureCallbacks() : array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getPassThroughCallbacks() : array
    {
        return [];
    }
}
