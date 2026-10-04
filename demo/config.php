<?php

/**
 * Pure helpers for the demo: no side effects, so they can be unit tested.
 * In your own application these are your framework's config and request
 * helpers. Nothing here is part of the package.
 */

declare(strict_types=1);

/**
 * The payment methods the demo can offer, and the .env switch for each.
 * Set a switch to 0 to stop offering that method.
 */
const DEMO_METHODS = [
    'card' => 'DEMO_ENABLE_CARD',
    'googlepay' => 'DEMO_ENABLE_GOOGLE_PAY',
    'applepay' => 'DEMO_ENABLE_APPLE_PAY',
    'paypal' => 'DEMO_ENABLE_PAY_PAL',
];

/**
 * Parse a simple KEY=value .env file. Blank lines, # comments and lines with
 * no "=" are skipped. Values keep any further "=".
 *
 * @return array<string, string>
 */
function parseEnvFile(string $path): array
{
    $values = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $values[trim($name)] = trim($value);
    }

    return $values;
}

/**
 * The methods this site offers. Each defaults to on.
 *
 * @param array<string, string> $env
 * @return list<string>
 */
function enabledMethods(array $env): array
{
    $enabled = [];

    foreach (DEMO_METHODS as $method => $switch) {
        if (($env[$switch] ?? '1') !== '0') {
            $enabled[] = $method;
        }
    }

    return $enabled;
}

/**
 * The URL this demo is served from, e.g. http://127.0.0.1:8000. Behind a
 * tunnel such as ngrok the scheme comes from X-Forwarded-Proto, so callback
 * URLs built from it are https.
 *
 * @param array<string, mixed> $server
 */
function baseUrl(array $server): string
{
    $https = ($server['HTTPS'] ?? '') !== '' && ($server['HTTPS'] ?? '') !== 'off';
    $forwarded = strtolower((string) ($server['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $scheme = ($https || $forwarded === 'https') ? 'https' : 'http';

    return $scheme . '://' . ($server['HTTP_HOST'] ?? '127.0.0.1:8000');
}

/**
 * The shopper's IP address, for 3D Secure (browserIP) and the wallet payment
 * methods. Behind a proxy or tunnel the connection comes from the proxy, and
 * the shopper's address is the first entry of X-Forwarded-For. That header is
 * only believed when the connection comes from the proxy itself (here, a
 * tunnel agent on this machine): anyone else could send whatever they like.
 * Behind your own load balancer, trust its address instead of loopback.
 *
 * @param array<string, mixed> $server
 */
function clientIp(array $server): string
{
    $remote = (string) ($server['REMOTE_ADDR'] ?? '127.0.0.1');

    if (in_array($remote, ['127.0.0.1', '::1'], true) && ! empty($server['HTTP_X_FORWARDED_FOR'])) {
        $original = trim(explode(',', (string) $server['HTTP_X_FORWARDED_FOR'])[0]);

        if (filter_var($original, FILTER_VALIDATE_IP) !== false) {
            return $original;
        }
    }

    return $remote;
}

/**
 * Opayo rejects "localhost" in the 3D Secure notification URL, and the session
 * cookie must survive the round trip, so localhost is sent to 127.0.0.1.
 *
 * @param array<string, mixed> $server
 */
function dottedHostRedirect(array $server): ?string
{
    $host = (string) ($server['HTTP_HOST'] ?? '');

    if (! str_starts_with($host, 'localhost')) {
        return null;
    }

    return 'http://' . str_replace('localhost', '127.0.0.1', $host) . ($server['REQUEST_URI'] ?? '/');
}

/**
 * The domain registered for Apple Pay in MyOpayo; defaults to the host the
 * demo is served from.
 *
 * @param array<string, string> $env
 * @param array<string, mixed> $server
 */
function applePayDomain(array $env, array $server): string
{
    if (($env['OPAYO_APPLE_PAY_DOMAIN'] ?? '') !== '') {
        return $env['OPAYO_APPLE_PAY_DOMAIN'];
    }

    return (string) ($server['HTTP_HOST'] ?? '127.0.0.1');
}

/**
 * Which method a payment POST is paying with, from the one credential it
 * carries. Null when there is none, or more than one.
 *
 * @param array<string, mixed> $post
 */
function postedMethod(array $post): ?string
{
    $found = array_keys(array_filter([
        'card' => ($post['card-identifier'] ?? '') !== '',
        'googlepay' => ($post['googlePayToken'] ?? '') !== '',
        'applepay' => ($post['applePayToken'] ?? '') !== '',
        'paypal' => ($post['method'] ?? '') === 'paypal',
    ]));

    return count($found) === 1 ? $found[0] : null;
}

/**
 * A stand-in for your order database: which Opayo transaction belongs to which
 * of your order references. The 3D Secure return must not rely on the session
 * cookie (a cross-site POST may arrive without it), so it looks the
 * transaction up from the order reference the issuer hands back.
 */
function rememberTransaction(string $dir, string $orderRef, string $transactionId): void
{
    if (! is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    file_put_contents($dir . '/' . sha1($orderRef), $transactionId);
}

function recallTransaction(string $dir, string $orderRef): ?string
{
    $file = $dir . '/' . sha1($orderRef);

    return is_file($file) ? (string) file_get_contents($file) : null;
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES);
}
