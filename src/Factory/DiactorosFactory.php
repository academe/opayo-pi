<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Factory;

/**
 * Zend Diactoros Factory for creating PSR-7 objects.
 * Requires zendframework/zend-diactoros:~1.3
 *
 * @deprecated 3.0.0 abandoned some time ago https://github.com/zendframework/zend-diactoros
 */

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Zend\Diactoros\Request;
use Zend\Diactoros\Stream;

class DiactorosFactory implements RequestFactoryInterface
{
    /**
     * Return a new GuzzleHttp\Psr7\Request object.
     * The body is to be sent as a JSON request.
     * @param string|null $method
     * @param UriInterface|string|null $uri
     * @param array $headers
     * @param mixed $body
     * @param string $protocolVersion
     * @return Request
     */
    public function jsonRequest(?string $method, UriInterface|string|null $uri, array $headers = [], mixed $body = null, string $protocolVersion = '1.1'): Request
    {
        // If we are sending a JSON body, then the recipient needs to know.
        $headers['Content-Type'] = 'application/json';

        // If the body is not already a stream or string of some sort, then JSON encode it for streaming.
        if (! is_string($body) && ! $body instanceof StreamInterface && gettype($body) != 'resource') {
            $body = json_encode($body);
        }

        // Create a stream for the body if a string.
        // Diactoros will treat a string as a resource URI and not as the body.
        if (is_string($body)) {
            $bodyStream = new Stream('php://memory', 'wb+');
            $bodyStream->write($body);
        } else {
            // CHECKME: will Diactoros accept a resource as a body?
            $bodyStream = $body;
        }

        return new Request(
            $uri,
            $method,
            $bodyStream,
            $headers
        );
    }

    /**
     * Check whether Guzzle is installed so this factory can be used.
     * @return bool
     */
    public static function isSupported(): bool
    {
        return class_exists(Request::class);
    }
}
