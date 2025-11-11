<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

use Academe\Opayo\Pi\Http\Stream;
use Academe\Opayo\Pi\Http\Uri;
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
     */
    protected array $httpHeaders = [
        'Content-Type' => ['application/json'],
    ];

    public function getRequestTarget(): string
    {
        return '/'; // TODO
    }

    public function withRequestTarget(string $requestTarget): static
    {
        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): static
    {
        return $this;
    }

    public function getUri(): UriInterface
    {
        return new Uri($this->getUrl());
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        return $this;
    }

    public function getProtocolVersion(): string
    {
        return '1.1';
    }

    public function withProtocolVersion(string $version): static
    {
        return $this;
    }

    /**
     * Merge the current header list with the required authentication
     * headers (which do change between some endpoints).
     *
     * @return array
     */
    public function getHeaders(): array
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
     * @return bool
     */
    public function hasHeader(string $name): bool
    {
        return array_key_exists(
            strtolower($name),
            array_change_key_case($this->httpHeaders, CASE_LOWER)
        );
    }

    public function getHeader(string $name): array
    {
        foreach ($this->httpHeaders as $key => $values) {
            if (strtolower($key) === strtolower($name)) {
                return $values;
            }
        }

        return [];
    }

    public function getHeaderLine(string $name): string
    {
        return ''; // @todo to be supported
    }

    public function withHeader(string $name, mixed $value): static
    {
        return $this; // @todo to be supported
    }

    public function withAddedHeader(string $name, mixed $value): static
    {
        return $this; // @todo to be supported
    }

    public function withoutHeader(string $name): static
    {
        return $this; // @todo to be supported
    }

    public function getBody(): StreamInterface
    {
        if (method_exists($this, 'jsonSerializePeek')) {
            $body = json_encode($this->jsonSerializePeek());
        } else {
            $body = json_encode($this);
        }

        return new Stream($body);
    }

    public function withBody(StreamInterface $body): static
    {
        return $this;
    }
}
