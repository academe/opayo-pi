<?php

namespace Academe\Opayo\Pi\Request;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Provides the request the methods needed to support the
 * request as a native PSR-7 request.
 * Many "with" methods are stubbed, i.e. not used.
 */

trait RequestPsr7Trait
{
    /**
     * Headers for all requests.
     * Basic Auth is added to this.
     *
     * @var array
     */
    protected $httpHeaders = [
        'Content-Type' => ['application/json'],
    ];

    public function getRequestTarget()
    {
        return '/'; // TODO
    }

    public function withRequestTarget($requestTarget)
    {
        return $this;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function withMethod($method)
    {
        return $this;
    }

    public function getUri()
    {
        return $this->getFactory(true)->uri($this->getUrl());
    }

    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        return $this;
    }

    public function getProtocolVersion()
    {
        return '1.1';
    }

    public function withProtocolVersion($version)
    {
        return $this;
    }

    /**
     * Merge the current header list with the required authentication
     * headers (which do change between some endpoints).
     *
     * @return void
     */
    public function getHeaders()
    {
        return array_merge(
            $this->getAuthHeaders(),
            $this->httpHeaders
        );
    }

    /**
     * Header keys should use a case-insensitive match.
     *
     * @param string $name
     * @return boolean
     */
    public function hasHeader($name)
    {
        return array_key_exists(
            strtolower($name),
            array_change_key_case($this->httpHeaders, CASE_LOWER)
        );
    }

    public function getHeader($name)
    {
        foreach ($this->httpHeaders as $key => $values) {
            if (strtolower($key === atrtolower($name))) {
                return $values;
            }
        }

        return [];
    }

    public function getHeaderLine($name)
    {
        return ''; // @todo to be supported
    }

    public function withHeader($name, $value)
    {
        return $this; // @todo to be supported
    }

    public function withAddedHeader($name, $value)
    {
        return $this; // @todo to be supported
    }

    public function withoutHeader($name)
    {
        return $this; // @todo to be supported
    }

    public function getBody()
    {
        if (method_exists($this, 'jsonSerializePeek')) {
            $body = json_encode($this->jsonSerializePeek());
        } else {
            $body = json_encode($this);
        }

        // Now turn it into a stream; need a factory.

        return $this->getFactory(true)->stream($body);
    }

    public function withBody(StreamInterface $body)
    {
        return $this;
    }
}
