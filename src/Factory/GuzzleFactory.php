<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Factory;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Guzzle Factory for creating PSR-7 objects.
 * Requires guzzlehttp/guzzle:~6.0
 *
 * @deprecated use any PSR-17 factory instead
 */

class GuzzleFactory implements RequestFactoryInterface
{
    /**
     * Return a new GuzzleHttp\Psr7\Request object.
     * The body is to be sent as a JSON request.
     *
     * @param string|null $method
     * @param UriInterface|string|null $uri
     * @param array $headers
     * @param mixed $body
     * @param string $protocolVersion
     * @return Request
     *
     * @deprecated no longer used since the request classes are now native PSR-7 requests
     */
    public function jsonRequest(?string $method, UriInterface|string|null $uri, array $headers = [], mixed $body = null, string $protocolVersion = '1.1'): Request
    {
        // If we are sending a JSON body, then the recipient needs to know.

        $headers['Content-Type'] = ['application/json'];

        // If the body is already a stream or string of some sort, then it is
        // assumed to already be a JSON stream.

        if (! is_string($body) && ! $body instanceof StreamInterface && gettype($body) != 'resource') {
            $body = json_encode($body);
        }

        // Guzzle will accept the body as a string and generate a stream from it.

        return new Request(
            $method,
            $uri,
            $headers,
            $body,
            $protocolVersion
        );
    }

    /**
     * Create a PSR-7 UriInterface object from a URL string.
     *
     * @param string $uri
     * @return Uri
     */
    public function uri(string $uri): Uri
    {
        return new Uri($uri);
    }

    /**
     * Create a PSR-7 stream from a string.
     *
     * @param mixed $stringData
     * @return StreamInterface
     */
    public function stream(mixed $stringData): StreamInterface
    {
        return Utils::streamFor($stringData);
    }

    /**
     * Check whether Guzzle PSR-7 is installed so this factory can be used.
     * Note: Guzzle does not support everything (e.g. not ServerRequestInterface at this time).
     *
     * @return bool
     * @todo this needs looking at again
     */
    public static function isSupported(): bool
    {
        return class_exists(Request::class);
    }
}
