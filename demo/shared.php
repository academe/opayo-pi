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
use Academe\Opayo\Pi\Request\Model\StrongCustomerAuthentication;
use Academe\Opayo\Pi\Request\Model\PaymentMethodInterface;
use Academe\Opayo\Pi\Request\Model\SingleUseCard;
use Academe\Opayo\Pi\Request\Model\GooglePayPayment;
use Academe\Opayo\Pi\Request\Model\ApplePayPayment;
use Academe\Opayo\Pi\Request\Model\PayPalPayment;
use Academe\Opayo\Pi\Request\Enums\ChallengeWindowSize;
use Academe\Opayo\Pi\Request\Enums\TransType;

// Guarded so the file is safe to require from a PHPUnit (CLI) context, where
// there is no request and starting a session would emit a headers warning.
if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_start();
}

/**
 * Load the .env file in the project root into $_ENV (same simple format
 * as tests/Integration/IntegrationTestCase).
 */
function loadEnv(): void
{
    $envFile = __DIR__ . '/../.env';

    if (! file_exists($envFile)) {
        // Under the web demo a missing .env is fatal; under CLI/tests just
        // return and let the caller populate $_ENV itself.
        if (PHP_SAPI === 'cli') {
            return;
        }
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

/**
 * Which demo panels are switched on. Defaults to enabled; set
 * DEMO_ENABLE_<METHOD>=0 in .env to hide one you are not working on.
 * DEMO_, not OPAYO_: this is about the demo page, not whether the wallet is
 * enabled on your Opayo vendor.
 *
 * @param string $method One of card|googlePay|applePay|payPal.
 */
function demoMethodEnabled(string $method): bool
{
    $key = 'DEMO_ENABLE_' . strtoupper(preg_replace('/([a-z])([A-Z])/', '$1_$2', $method));

    return ($_ENV[$key] ?? '1') !== '0';
}

/**
 * The domain Apple Pay merchant validation runs against. Defaults to the host
 * the demo is served from, which is why 127.0.0.1 yields "6118 Domain not
 * registered" until a real registered domain is configured.
 */
function applePayDomain(): string
{
    if (! empty($_ENV['OPAYO_APPLE_PAY_DOMAIN'])) {
        return $_ENV['OPAYO_APPLE_PAY_DOMAIN'];
    }

    return preg_replace('#^https?://#', '', baseUrl());
}

/**
 * Turn whatever credential the browser posted into a payment method. The
 * checkout panels post exactly one of these; this is the single place in the
 * demo where the method matters, and everything after it is identical.
 */
function paymentMethodFromRequest(array $post, string $sessionKey, string $clientIp): PaymentMethodInterface
{
    if (! empty($post['card-identifier'])) {
        return new SingleUseCard($sessionKey, $post['card-identifier']);
    }

    if (! empty($post['googlePayToken'])) {
        return GooglePayPayment::fromGoogleToken($sessionKey, $clientIp, $post['googlePayToken']);
    }

    if (! empty($post['applePayToken'])) {
        return ApplePayPayment::fromAppleToken(
            $sessionKey,
            $clientIp,
            $post['applePayToken'],
            $post['appleSessionValidationToken'] ?? null
        );
    }

    if (($post['method'] ?? '') === 'paypal') {
        return new PayPalPayment($sessionKey, baseUrl() . '/paypal-return.php');
    }

    throw new \InvalidArgumentException('No payment credential in the request.');
}

/**
 * The strongCustomerAuthentication object, built from the browser fields the
 * checkout page collected. Sent on every payment: you cannot know in advance
 * whether a Google PAN_ONLY token will be challenged, so always provide it.
 */
function scaFromRequest(array $post, string $notificationUrl, string $clientIp): StrongCustomerAuthentication
{
    return new StrongCustomerAuthentication(
        $notificationUrl,
        str_contains($clientIp, ':') ? '127.0.0.1' : $clientIp, // IPv4 only
        $_SERVER['HTTP_ACCEPT'] ?? '*/*',
        true,
        ($post['browserLanguage'] ?? '') ?: 'en-GB',
        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        ChallengeWindowSize::Medium,
        TransType::GoodsAndServicePurchase,
        [
            'browserJavaEnabled' => false,
            'browserColorDepth' => (int)($post['browserColorDepth'] ?? 24),
            'browserScreenHeight' => (int)($post['browserScreenHeight'] ?? 0),
            'browserScreenWidth' => (int)($post['browserScreenWidth'] ?? 0),
            'browserTz' => (int)($post['browserTz'] ?? 0),
        ]
    );
}

// ---------------------------------------------------------------------------
// Readiness: can this shopper pay this way, right now? Each probe returns the
// same shape - {available, reason, detail} - so every panel treats them alike.
// Layers, cheapest first: demo flag, config, gateway, browser. A method that
// cannot run is shown as a placeholder stating why, never hidden. A probe never
// overclaims: "Google Pay ready" means Google is ready, not that Opayo is
// enrolled.
// ---------------------------------------------------------------------------

/**
 * @return array{available: bool, reason: ?string, detail: ?string}
 */
function cardReadiness(): array
{
    if (! demoMethodEnabled('card')) {
        return ['available' => false, 'reason' => 'Switched off in .env (DEMO_ENABLE_CARD=0)', 'detail' => null];
    }

    return ['available' => true, 'reason' => null, 'detail' => null];
}

/**
 * @return array{available: bool, reason: ?string, detail: ?string}
 */
function googlePayReadiness(): array
{
    if (! demoMethodEnabled('googlePay')) {
        return ['available' => false, 'reason' => 'Switched off in .env (DEMO_ENABLE_GOOGLE_PAY=0)', 'detail' => null];
    }

    if (googlePayMerchantId() === '') {
        return [
            'available' => false,
            'reason' => 'No gatewayMerchantId configured',
            'detail' => 'Set OPAYO_GOOGLE_PAY_MERCHANT_ID (MyOpayo > Settings > Pay Methods > Google Pay).',
        ];
    }

    // Opayo has no pre-flight for Google Pay enrolment - the browser decides
    // readiness (isReadyToPay), and enrolment is only proven by paying.
    return [
        'available' => true,
        'reason' => null,
        'detail' => 'Browser readiness is checked by Google below. Opayo enrolment for this vendor is only '
                  . 'tested by paying: a vendor without the wallet fails at 6401 on click.',
    ];
}

/**
 * The one wallet whose render-time probe is a real Opayo call: a merchant
 * session either opens or it does not, and the error is worth showing.
 *
 * @return array{available: bool, reason: ?string, detail: ?string}
 */
function applePayReadiness(): array
{
    if (! demoMethodEnabled('applePay')) {
        return ['available' => false, 'reason' => 'Switched off in .env (DEMO_ENABLE_APPLE_PAY=0)', 'detail' => null];
    }

    try {
        $response = sendAndRecord(
            new \Academe\Opayo\Pi\Request\CreateApplePaySession(opayoEndpoint(), opayoAuth(), applePayDomain()),
            'Apple Pay merchant session (readiness probe)'
        );
    } catch (\Throwable $e) {
        return ['available' => false, 'reason' => 'Apple Pay session probe failed', 'detail' => $e->getMessage()];
    }

    if ($response instanceof \Academe\Opayo\Pi\Response\ApplePaySession && $response->getMerchantSession()) {
        return [
            'available' => true,
            'reason' => null,
            'detail' => 'Opayo returned a merchant session for ' . applePayDomain()
                      . '. The button still needs Safari on an Apple device.',
        ];
    }

    // A failure comes back as an ErrorCollection. Quote what Opayo actually
    // said rather than guessing a code: on 127.0.0.1 the host carries a port
    // and you get "6125 Invalid domainName field"; on a bare unregistered
    // domain you get "6118 Domain not registered". Both are correct outcomes
    // for a demo that is not yet served from a registered HTTPS domain.
    [$code, $description] = firstError($response);

    return [
        'available' => false,
        'reason' => 'Opayo will not open a merchant session' . ($code ? " ($code)" : ''),
        'detail' => ($description ?: 'Register the domain in MyOpayo > Settings > Pay Methods > Apple Pay.')
                  . ' Expected until the demo is served from a registered HTTPS domain'
                  . ' (OPAYO_APPLE_PAY_DOMAIN); 127.0.0.1 with a port is not a valid domain.',
    ];
}

/**
 * The first error's code and description from an ErrorCollection (or any
 * response that is not one). Returns [?string $code, ?string $description].
 *
 * @return array{0: ?string, 1: ?string}
 */
function firstError(mixed $response): array
{
    if ($response instanceof \Academe\Opayo\Pi\Response\ErrorCollection) {
        foreach ($response as $error) {
            $data = $error->jsonSerialize();
            return [$data['code'] ?? null, $data['description'] ?? null];
        }
    }

    return [null, null];
}

/**
 * @return array{available: bool, reason: ?string, detail: ?string}
 */
function payPalReadiness(): array
{
    if (! demoMethodEnabled('payPal')) {
        return ['available' => false, 'reason' => 'Switched off in .env (DEMO_ENABLE_PAY_PAL=0)', 'detail' => null];
    }

    return [
        'available' => true,
        'reason' => null,
        'detail' => 'Enrolment is only visible after redirect: an unenrolled vendor fails at 1030.',
    ];
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

/**
 * The gatewayMerchantId the Google Pay sheet quotes back to Opayo. MyOpayo
 * shows it under Settings > Pay Methods > Google Pay; in Google's TEST
 * environment any string is accepted, so the vendor name is a usable default.
 */
function googlePayMerchantId(): string
{
    return $_ENV['OPAYO_GOOGLE_PAY_MERCHANT_ID'] ?? vendorName();
}

/**
 * The Google merchant ID (Google Pay & Wallet Console). Only needed when the
 * sheet runs in PRODUCTION, which is also the only environment that mints a
 * real, decryptable token - see demo/README.md.
 */
function googlePayGoogleMerchantId(): string
{
    return $_ENV['GOOGLE_PAY_MERCHANT_ID'] ?? '';
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
        new CreateSessionKey(opayoEndpoint(), opayoAuth()),
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

// ---------------------------------------------------------------------------
// Outcome renderers. pay.php calls exactly one of these per response, whatever
// the payment method was. Consolidated here so there is one place that knows
// how each Opayo outcome looks on the page.
// ---------------------------------------------------------------------------

/**
 * A successful or declined transaction (Response\Payment and friends).
 */
function renderResult(mixed $response): void
{
    $ok = method_exists($response, 'isSuccessful') && $response->isSuccessful();
    $colour = $ok ? 'text-emerald-600' : 'text-red-600';

    echo '<div class="bg-white rounded-xl shadow p-6 space-y-2">';
    echo '<h2 class="text-lg font-semibold ' . $colour . '">'
        . ($ok ? 'Payment successful' : 'Payment not authorised') . '</h2>';

    $rows = [
        'Status' => $response->getStatus(),
        'Status detail' => $response->getStatusDetail(),
        'Transaction ID' => $response->getTransactionId(),
    ];
    if (method_exists($response, 'get3DSecureStatus')) {
        $rows['3D Secure'] = $response->get3DSecureStatus();
    }

    echo '<dl class="text-sm grid grid-cols-[10rem_1fr] gap-y-1">';
    foreach ($rows as $label => $value) {
        echo '<dt class="text-slate-500">' . h($label) . '</dt><dd class="font-mono">' . h((string)$value) . '</dd>';
    }
    echo '</dl>';

    // The sandbox rejects 3D Secure before any challenge in two easily-hit
    // situations; explain rather than leave the user guessing.
    if (! $ok && str_contains((string)$response->getStatusDetail(), '3D-Authentication failed')) {
        echo <<<EXPLAIN
        <div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg p-4 space-y-2">
            <p class="font-semibold">Rejected before the 3D Secure page? Two common causes:</p>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Account:</strong> personal test accounts have no 3DS simulation and
                    reject every attempt. Switch the account selector to
                    <em>Public sandbox (3DS simulation)</em>.</li>
                <li><strong>Cardholder name:</strong> the sandbox reads the 3DS outcome from the
                    name on the card. Anything that is not a magic value simulates a
                    <em>failed</em> authentication. Use <code>CHALLENGE</code> to get the
                    challenge page (in the drop-in, type it as the name in the card form -
                    the checkbox cannot reach inside Opayo's iframe to set it for you).</li>
            </ul>
        </div>
        EXPLAIN;
    }

    echo '</div>';
}

/**
 * An ErrorCollection (or any non-transaction failure). Keeps the teaching
 * notes for the wallet-specific codes the demo reliably hits.
 */
function renderErrors(mixed $response, string $heading): void
{
    echo '<div class="bg-white rounded-xl shadow p-6 space-y-3">';
    echo '<h2 class="text-lg font-semibold text-red-600">' . h($heading) . '</h2>';

    $joined = '';

    if ($response instanceof \Academe\Opayo\Pi\Response\ErrorCollection) {
        echo '<ul class="text-sm text-slate-700 list-disc pl-5 space-y-1">';
        foreach ($response as $error) {
            $detail = $error->jsonSerialize();
            $joined .= ' ' . ($detail['description'] ?? '');
            echo '<li>' . h($detail['description'] ?? 'Unknown error')
                . ' <span class="text-slate-400">(' . h((string)($detail['code'] ?? $detail['property'] ?? '-')) . ')</span></li>';
        }
        echo '</ul>';
    }

    explainKnownFailures($joined);

    echo '<a href="index.php" class="inline-block text-sm text-blue-600 hover:underline">&larr; Back to checkout</a>';
    echo '</div>';
}

/**
 * The wallet-specific failures the demo reliably reaches, and what each means.
 */
function explainKnownFailures(string $detail): void
{
    if (str_contains($detail, 'Invalid Google Pay payload')) {
        echo <<<GPAY
        <div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg p-4 space-y-2">
            <p class="font-semibold">6203: as far as this demo can get.</p>
            <p>The vendor <em>is</em> enrolled for Google Pay - the payload was read, and rejected.
               Google's TEST environment returns the placeholder token
               <code>examplePaymentMethodToken</code> rather than an encrypted one, and Opayo has
               nothing to decrypt. Everything up to this point is a real, correctly formed
               Google Pay transaction: see the wire panel.</p>
            <p>Getting past it needs a PRODUCTION sheet, which in turn needs a Google merchant ID
               from the Google Pay &amp; Wallet Console and an allowlisted HTTPS origin.</p>
        </div>
        GPAY;
    }

    if (str_contains($detail, 'Wallet not enabled') || str_contains($detail, 'not enrolled')) {
        echo '<div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg p-4">'
            . '6401 / 1030: the wallet is not enabled on this vendor. Switch the account selector to '
            . 'the public sandbox, or ask Opayo to enable it on your vendor.</div>';
    }
}

/**
 * A 3D Secure v2 challenge: an auto-described form the browser POSTs to the
 * issuer's ACS, which returns to notification.php.
 */
function renderAcsRedirect(\Academe\Opayo\Pi\Response\Secure3Dv2Redirect $response, string $vendorTxCode): void
{
    // Do not use the transactionId as session data - Opayo rejects it.
    $paFields = $response->getPaRequestFields(base64_encode($vendorTxCode));

    recordWire('3D Secure redirect (browser POSTs this to the ACS)', [
        'acsUrl' => $response->getAcsUrl(),
        'fields' => $paFields,
    ]);

    $acsUrl = h($response->getAcsUrl());
    $inputs = '';
    foreach ($paFields as $name => $value) {
        $inputs .= '<input type="hidden" name="' . h($name) . '" value="' . h($value) . '">';
    }

    echo <<<ACS
    <div class="bg-white rounded-xl shadow p-6 space-y-4">
        <h2 class="text-lg font-semibold text-amber-600">3D Secure authentication required</h2>
        <p class="text-sm text-slate-600">
            The gateway responded with status <code>3DAuth</code>. Your browser now POSTs the
            <code>creq</code> to the card issuer's Access Control Server (ACS). After the
            challenge, the ACS sends your browser back to <code>notification.php</code>.
        </p>
        <form method="post" action="{$acsUrl}">
            {$inputs}
            <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2 rounded-lg">
                Continue to the 3D Secure challenge
            </button>
        </form>
    </div>
    ACS;
}

/**
 * A PayPal redirect (status Redirect, 2023): a full-page hop to PayPal, which
 * returns the shopper to paypal-return.php.
 */
function renderPayPalRedirect(\Academe\Opayo\Pi\Response\PayPalRedirect $response): void
{
    $orderId = h((string)$response->getOrderId());
    $redirectUrl = h((string)$response->getRedirectUrl());
    $transactionId = h((string)$response->getTransactionId());
    $base = h(baseUrl());

    echo <<<PP
    <div class="bg-white rounded-xl shadow p-6 space-y-4">
        <h2 class="text-lg font-semibold text-amber-600">Redirect to PayPal</h2>
        <p class="text-sm text-slate-700">
            The gateway responded with status <code>Redirect</code> (2023): the transaction is
            registered and the shopper must approve it at PayPal. This must be a full-page
            redirect (PayPal refuses to run in an iframe). PayPal order ID <code>{$orderId}</code>.
        </p>
        <p class="text-sm text-slate-700">
            At the PayPal <em>sandbox</em> you need a PayPal sandbox <strong>buyer</strong> login
            (free: developer.paypal.com &rarr; Sandbox &rarr; Accounts). After approving, PayPal hands
            back to Opayo, which redirects you to <code>{$base}/paypal-return.php</code>.
        </p>
        <a href="{$redirectUrl}"
           class="inline-block bg-blue-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-blue-700">
            Continue to PayPal
        </a>
        <p class="text-xs text-slate-500">
            Or, to see the callback handling without a PayPal login, open
            <a class="text-blue-600 hover:underline" href="paypal-return.php?transactionId={$transactionId}">paypal-return.php
            with this transactionId</a>. Until PayPal reports back, Opayo answers
            <code>404 Transaction not found</code> for it.
        </p>
    </div>
    PP;
}
