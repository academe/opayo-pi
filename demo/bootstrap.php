<?php

/**
 * Everything an integration configures, in one place. In your application
 * this is your framework's config and service container.
 *
 * Every demo page requires this file and then has:
 *
 *   $endpoint        the Opayo endpoint (test or live)
 *   $auth            your vendor name, integration key and password
 *   $client          any PSR-18 HTTP client (Guzzle here)
 *   $enabledMethods  the payment methods this site offers
 *   $baseUrl         where this site is served from (for callback URLs)
 *   $config          the settings individual methods need
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';

use Academe\Opayo\Pi\Factory\ResponseFactory;
use Academe\Opayo\Pi\GooglePay\Environment as GooglePayEnvironment;
use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Request\CreateSessionKey;
use Academe\Opayo\Pi\Response\SessionKey;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Http\Client\ClientInterface;

session_start();

$envFile = __DIR__ . '/../.env';
if (! is_file($envFile)) {
    http_response_code(500);
    exit('No .env file found. Copy .env.example to .env and add your Opayo credentials.');
}
$_ENV = $_ENV + parseEnvFile($envFile);

if (($target = dottedHostRedirect($_SERVER)) !== null) {
    header('Location: ' . $target);
    exit;
}

$handler = HandlerStack::create();

// Demo only: wire panel, setup checks, test tools.
// Delete this line and demo/debug/ to get the bare integration.
if (($_ENV['DEMO_DEBUG'] ?? '1') !== '0') require __DIR__ . '/debug/enable.php';

$endpoint = new Endpoint(($_ENV['OPAYO_ENVIRONMENT'] ?? 'test') === 'live' ? Endpoint::MODE_LIVE : Endpoint::MODE_TEST);
$auth = new Auth($_ENV['OPAYO_VENDOR_NAME'], $_ENV['OPAYO_INTEGRATION_KEY'], $_ENV['OPAYO_INTEGRATION_PASSWORD']);
$client = new Client(['handler' => $handler, 'http_errors' => false]);

$enabledMethods = enabledMethods($_ENV);
$baseUrl = baseUrl($_SERVER);

// Where the demo records which Opayo transaction belongs to which order. In
// your application this is your orders table.
$orderStore = sys_get_temp_dir() . '/opayo-pi-demo-orders';

$config = [
    'merchantName' => 'Opayo Pi Demo',
    // MyOpayo > Settings > Pay Methods > Google Pay. Not a secret.
    'googlePayMerchantId' => ($_ENV['OPAYO_GOOGLE_PAY_MERCHANT_ID'] ?? '') ?: $_ENV['OPAYO_VENDOR_NAME'],
    // Google Pay & Wallet Console; only read in PRODUCTION.
    'googleMerchantId' => ($_ENV['GOOGLE_PAY_MERCHANT_ID'] ?? '') ?: null,
    'googlePayEnvironment' => GooglePayEnvironment::tryFrom(strtoupper($_ENV['GOOGLE_PAY_ENVIRONMENT'] ?? 'TEST'))
        ?? GooglePayEnvironment::Test,
    // The domain registered in MyOpayo > Settings > Pay Methods > Apple Pay.
    'applePayDomain' => applePayDomain($_ENV, $_SERVER),
    // Card payments only: UseMSPSetting, Force, Disable or ForceIgnoringRules.
    'apply3DSecure' => ($_ENV['OPAYO_APPLY_3D_SECURE'] ?? '') ?: 'UseMSPSetting',
];

/**
 * An Opayo merchant session key: every payment needs one (it lasts about 400
 * seconds and allows three uses).
 */
function merchantSessionKey(ClientInterface $client, Endpoint $endpoint, Auth $auth): string
{
    $response = ResponseFactory::fromHttpResponse(
        $client->sendRequest(new CreateSessionKey($endpoint, $auth))
    );

    if (! $response instanceof SessionKey) {
        throw new RuntimeException('Opayo would not issue a merchant session key. Check your credentials in .env.');
    }

    return $response->getMerchantSessionKey();
}
