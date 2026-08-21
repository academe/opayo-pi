<?php

/**
 * Shared helpers for the demo scripts.
 *
 * Run the demo from the repository root with:
 *
 *   php -S 127.0.0.1:8000 -t demo
 *
 * Credentials come from the same .env file the integration tests use
 * (copy .env.example to .env and fill in your Opayo test account details).
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Request\CreateSessionKey;
use Academe\Opayo\Pi\Response\SessionKey;
use Academe\Opayo\Pi\Factory\ResponseFactory;
use GuzzleHttp\Client;
use Psr\Http\Message\RequestInterface;

session_start();

/**
 * Load the .env file in the project root into $_ENV (same simple format
 * as tests/Integration/IntegrationTestCase).
 */
function loadEnv(): void
{
    $envFile = __DIR__ . '/../.env';

    if (! file_exists($envFile)) {
        http_response_code(500);
        exit('No .env file found. Copy .env.example to .env and add your Opayo test credentials.');
    }

    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] ??= trim($value);
    }
}

loadEnv();

/**
 * The public "Basic" sandbox profile documented on the Elavon developer
 * portal (developer.elavon.com, "Test in Sandbox"). These credentials are
 * published openly by Elavon. Unlike a personal test account, this shared
 * profile has the magic-cardholder 3D Secure simulation enabled, so it is
 * the one to use when trying the 3DS flows.
 */
const PUBLIC_SANDBOX_PROFILE = [
    'vendorName' => 'sandbox',
    'integrationKey' => 'hJYxsw7HLbj40cB8udES8CDRFLhuJ8G54O6rDpUXvE6hYDrria',
    'integrationPassword' => 'o2iHSrFybYMZpmWOQMuhsXP52V4fBtpuSDshrKDSWsBY1OiN6hwd9Kb12z4j5Us5u',
];

/**
 * Which account this flow is using: "env" (your .env credentials, same as
 * the integration tests) or "sandbox" (the public profile above).
 * The choice is kept in the session so notification.php, which receives
 * only the ACS POST, completes the challenge against the same account.
 */
function demoAccount(): string
{
    $account = ($_REQUEST['account'] ?? $_SESSION['account'] ?? 'env') === 'sandbox' ? 'sandbox' : 'env';
    $_SESSION['account'] = $account;

    return $account;
}

function vendorName(): string
{
    return demoAccount() === 'sandbox'
        ? PUBLIC_SANDBOX_PROFILE['vendorName']
        : $_ENV['OPAYO_VENDOR_NAME'];
}

function opayoAuth(): Auth
{
    if (demoAccount() === 'sandbox') {
        return new Auth(
            PUBLIC_SANDBOX_PROFILE['vendorName'],
            PUBLIC_SANDBOX_PROFILE['integrationKey'],
            PUBLIC_SANDBOX_PROFILE['integrationPassword']
        );
    }

    return new Auth(
        $_ENV['OPAYO_VENDOR_NAME'],
        $_ENV['OPAYO_INTEGRATION_KEY'],
        $_ENV['OPAYO_INTEGRATION_PASSWORD']
    );
}

function opayoEndpoint(): Endpoint
{
    return new Endpoint(Endpoint::MODE_TEST);
}

function httpClient(): Client
{
    static $client;
    return $client ??= new Client(['http_errors' => false]);
}

/**
 * The base URL of this demo (e.g. http://127.0.0.1:8000), used to build
 * the 3D Secure notification URL. The ACS sends the challenge result via
 * the shopper's BROWSER, so a local address is reachable - no public URL
 * needed. Note: Opayo's URL validation rejects bare "localhost" (it wants
 * a dotted hostname), which is why the demo runs on 127.0.0.1.
 */
function baseUrl(): string
{
    return 'http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000');
}

/**
 * The 3D Secure return must land on the same host the shopper started on,
 * or the PHP session cookie (holding the transactionId) will not be sent.
 * Opayo rejects "localhost" in the notification URL, so pin everything
 * to 127.0.0.1.
 */
function requireDottedHost(): void
{
    $host = $_SERVER['HTTP_HOST'] ?? '';

    if (str_starts_with($host, 'localhost')) {
        $target = 'http://' . str_replace('localhost', '127.0.0.1', $host) . $_SERVER['REQUEST_URI'];
        header("Location: $target");
        exit;
    }
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES);
}

// ---------------------------------------------------------------------------
// The "wire data" panel: every raw payload that flows is recorded here and
// rendered on the right-hand side of the page.
// ---------------------------------------------------------------------------

/** @var array<int, array{label: string, data: mixed}> */
$GLOBALS['wire'] = [];

function recordWire(string $label, mixed $data): void
{
    $GLOBALS['wire'][] = ['label' => $label, 'data' => $data];
}

/**
 * Send a PSR-7 request, recording the raw request and response bodies in
 * the wire panel. Returns the parsed response model.
 */
function sendAndRecord(RequestInterface $request, string $label): mixed
{
    recordWire("$label - request " . $request->getMethod() . ' ' . $request->getUri()->getPath(), json_decode((string)$request->getBody()));

    $httpResponse = httpClient()->sendRequest($request);

    recordWire("$label - response HTTP " . $httpResponse->getStatusCode(), json_decode((string)$httpResponse->getBody()));

    return ResponseFactory::fromHttpResponse($httpResponse);
}

/**
 * Create a merchant session key (valid ~400 seconds, 3 uses).
 */
function createMerchantSessionKey(): string
{
    $response = sendAndRecord(
        new CreateSessionKey(opayoEndpoint(), opayoAuth(), vendorName()),
        'Merchant session key'
    );

    if (! $response instanceof SessionKey) {
        exit('Could not create a merchant session key. Check your .env credentials.');
    }

    return $response->getMerchantSessionKey();
}

// ---------------------------------------------------------------------------
// Page layout: forms and actions on the left, wire data on the right.
// ---------------------------------------------------------------------------

function pageTop(string $title): void
{
    echo <<<HTML
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$title} - Opayo Pi Demo</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-slate-100 min-h-screen">
        <div class="max-w-6xl mx-auto p-6">
            <header class="mb-6">
                <h1 class="text-2xl font-bold text-slate-800">Opayo Pi Demo <span class="text-slate-400 font-normal">/ {$title}</span></h1>
                <p class="text-sm text-slate-500">Sandbox only - use the Opayo test cards. Forms on the left, raw wire data on the right.</p>
            </header>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <main class="space-y-6">
    HTML;
}

function pageBottom(): void
{
    echo '</main><aside class="space-y-4">';

    echo '<h2 class="text-lg font-semibold text-slate-700">Wire data</h2>';

    if (empty($GLOBALS['wire'])) {
        echo '<p class="text-sm text-slate-500">Nothing sent yet.</p>';
    }

    foreach ($GLOBALS['wire'] as $entry) {
        $label = h($entry['label']);
        $json = h(json_encode($entry['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo <<<HTML
        <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">{$label}</div>
            <pre class="bg-slate-900 text-emerald-300 text-xs rounded-lg p-3 overflow-x-auto">{$json}</pre>
        </div>
        HTML;
    }

    // What is being carried between requests in the PHP session.
    $session = h(json_encode($_SESSION, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo <<<HTML
    <div>
        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">PHP \$_SESSION (stored between steps)</div>
        <pre class="bg-slate-800 text-sky-300 text-xs rounded-lg p-3 overflow-x-auto">{$session}</pre>
    </div>
    </aside></div></div></body></html>
    HTML;
}
