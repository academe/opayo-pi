<?php

namespace Academe\Opayo\Pi\Factory;

/**
 * Factory interface for creating PSR-7 request objects.
 * The implementation will often be Guzzle (GuzzleFactory), but the interface
 * allows other implementations to be used.
 *
 * The factory does handle HTTP clients. That is left entirely for the application.
 * The HTTP clients are entirely responsible for creating PSR-7 Response objects.
 *
 * A client and client factory may be a PSR recommendation at some point, and we will
 * support that when it happens.
 */

use Psr\Http\Message\RequestInterface;

interface RequestFactoryInterface
{
    /**
     * Return a new PSR-7 Request object, with the body to be sent as JSON.
     *
     * @param null|string $method HTTP method for the request.
     * @param null|string|UriInterface $uri URI for the request.
     * @param array $headers Headers for the message.
     * @param string|array|resource|StreamInterface $body Message body.
     * @param string $protocolVersion HTTP protocol version.
     * @return RequestInterface The PSR-7 request message
     */
    public function jsonRequest(
        $method,
        $uri,
        array $headers = [],
        $body = null,
        $protocolVersion = '1.1'
    );

    /**
     * Check whether the required libraries are installed so this factory can be used.
     * @return boolean True if the libraries are installed to support this PSR-7 implementation.
     */
    public static function isSupported();
}
