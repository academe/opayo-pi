<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Model;

/**
 * The endpoint to use to access the Sage Pay API.
 * TODO: support PSR-7 Url objects as well as strings.
 * Using the PSR-7 UriInterface seens like it could be useful, but we still have to
 * mess around constructing a path from strings and handling encoding, so maybe just
 * bypass the UriInterface.
 */

//use Exception;
use UnexpectedValueException;

class Endpoint
{
    /**
     * This release is locked onto just one API version.
     * It is likely beta will remain v1 for its entire lifetime.
     */
    protected string $api_version = 'v1';

    /**
     * Modes of operation.
     */
    public const MODE_LIVE = 1;
    public const MODE_TEST = 2;

    /**
     * @var array<int, string> The endpoint URL templates, one for each mode.
     */
    protected array $urls_templates = [
        1 => 'https://live.opayo.eu.elavon.com/api/{version}{resource}',
        2 => 'https://sandbox.opayo.eu.elavon.com/api/{version}{resource}',
    ];

    /**
     * @param int $mode The mode of operation (whether test or production)
     */
    public function __construct(
        protected int $mode = self::MODE_LIVE
    ) {
        // The mode - testing or production. Possible others later.
        if (! isset($this->urls_templates[$mode])) {
            throw new UnexpectedValueException(sprintf('Unexpected mode value "%s"', $mode));
        }
    }

    /**
     * @param string $version
     * @return static Clone of $this with the new API version set.
     */
    public function withApiVersion(string $version): static
    {
        $clone = clone $this;
        $clone->api_version = $version;
        return $clone;
    }

    /**
     * @return string The API version
     */
    public function getApiVersion(): string
    {
        return $this->api_version;
    }

    /**
     * Override any of the URLs.
     * Supports replacement fields {version} and {resource}
     *
     * @param int $mode The mode to set the endpoint URL for
     * @param string $url The absolute URL or URL template with placeholders
     *
     * @return static A clone of $this with the new URL or URL template set
     */
    public function withUrl(int $mode, string $url): static
    {
        if (! isset($this->urls_templates[$mode])) {
            throw new UnexpectedValueException(sprintf('Unexpected mode value "%s"', $mode));
        }

        $copy = clone $this;
        $copy->urls_templates[$mode] = $url;
        return $copy;
    }

    /**
     * Get the endpoint URL.
     * A resource can be supplied as a string or array (if it has multiple path parts).
     * String resources must include a "/" prefix and be ready-encoded for the URL.
     * A resource as an array should not have directory separators included, and will
     * be url encoded here, so should not be done in advance.
     *
     * @param string|array<string> $resource The name of the resource
     *
     * @return string The absolute endpoint URL
     */
    public function getUrl(string|array $resource = ''): string
    {
        // If the resource is an array, then combine it into the path.
        if (is_array($resource)) {
            // Encode all parts of the path.
            $resource = '/' . implode('/', array_map('rawurlencode', $resource));
        } else {
            if ($resource !== '' && !str_starts_with($resource, '/')) {
                $resource = '/' . $resource;
            }
        }

        return str_replace(
            ['{version}', '{resource}'],
            [$this->getApiVersion(), $resource],
            $this->urls_templates[$this->mode]
        );
    }

    /**
     * Get the URL for sagepay.js - the card tokeniser and security code linker for the front end.
     *
     * @return string The URL to the JavaScript front end resource on the Sage Pay gateway.
     */
    public function getJavascriptUrl(): string
    {
        return $this->getUrl(['js', 'sagepay.js']);
    }

    /**
     * Get the URL for sagepay-dropin.js - the drop-in form handler for the front end.
     *
     * @return string The URL to the JavaScript front end resource on the Sage Pay gateway.
     */
    public function getDropinJavascriptUrl(): string
    {
        return $this->getUrl(['js', 'sagepay-dropin.js']);
    }

    /**
     * Return a testing instance (since it was an optional setting on first instantiation).
     *
     * @return static A clone of $this with test mode set
     */
    public function withTestingMode(): static
    {
        $copy = clone $this;
        $copy->mode = static::MODE_TEST;
        return $copy;
    }

    /**
     * Indicates whether we are using a test account.
     *
     * @return bool True if we are in testing mode, otherwise False
     */
    public function isTesting(): bool
    {
        return $this->mode === static::MODE_TEST;
    }
}
