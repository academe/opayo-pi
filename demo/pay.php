<?php

/**
 * The pay endpoint. Every payment method posts here, and every method follows
 * the same steps; only step 2 differs between them.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Academe\Opayo\Pi\Checkout\BrowserData;
use Academe\Opayo\Pi\Checkout\OutcomeKind;
use Academe\Opayo\Pi\Checkout\PaymentOutcome;
use Academe\Opayo\Pi\Factory\ResponseFactory;
use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Money\Currency;
use Academe\Opayo\Pi\Request\CreatePayment;
use Academe\Opayo\Pi\Request\Enums\EntryMethod;
use Academe\Opayo\Pi\Request\Model\Address;
use Academe\Opayo\Pi\Request\Model\ApplePayPayment;
use Academe\Opayo\Pi\Request\Model\GooglePayPayment;
use Academe\Opayo\Pi\Request\Model\PayPalPayment;
use Academe\Opayo\Pi\Request\Model\Person;
use Academe\Opayo\Pi\Request\Model\SingleUseCard;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: checkout.php');
    exit;
}

$json = ($_POST['resultFormat'] ?? '') === 'json';

$refuse = static function (string $reason) use ($json): never {
    http_response_code(400);
    if ($json) {
        header('Content-Type: application/json');
        exit(json_encode(['approved' => false, 'error' => $reason]));
    }
    exit($reason);
};

// 1. Which method is this, and does this site offer it? Check here, not just
//    in the page: the server decides what it accepts.
$method = postedMethod($_POST);
if ($method === null || ! in_array($method, $enabledMethods, true)) {
    $refuse('This payment method is not offered.');
}

// The shopper's address, not the tunnel's or proxy's: see clientIp().
$clientIp = clientIp($_SERVER);

// The card drop-in made its session key at checkout (it tokenised the card
// with it); every other method needs a fresh one.
$sessionKey = ($_POST['merchantSessionKey'] ?? '') ?: merchantSessionKey($client, $endpoint, $auth);

// 2. The payment method: the only line that differs between methods.
try {
    $paymentMethod = match ($method) {
        'card' => new SingleUseCard($sessionKey, $_POST['card-identifier']),
        'googlepay' => GooglePayPayment::fromGoogleToken($sessionKey, $clientIp, $_POST['googlePayToken']),
        'applepay' => ApplePayPayment::fromAppleToken(
            $sessionKey,
            $clientIp,
            $_POST['applePayToken'],
            ($_POST['appleSessionValidationToken'] ?? '') ?: null
        ),
        'paypal' => new PayPalPayment($sessionKey, $baseUrl . '/paypal-return.php'),
    };
} catch (InvalidArgumentException $e) {
    // A token the package cannot read, e.g. not what the wallet gave the browser.
    $refuse('The payment details could not be read: ' . $e->getMessage());
}

// 3. 3D Secure data from the shopper's browser, sent for every method: a
//    wallet token can be challenged too.
$strongCustomerAuthentication = BrowserData::fromArray($_POST)->toStrongCustomerAuthentication(
    $baseUrl . '/notification.php',
    $clientIp,
    $_SERVER['HTTP_ACCEPT'] ?? '',
    $_SERVER['HTTP_USER_AGENT'] ?? ''
);

$options = [
    'entryMethod' => EntryMethod::Ecommerce,
    'strongCustomerAuthentication' => $strongCustomerAuthentication,
];

// Card only: wallet tokens are already authenticated on the device.
if ($method === 'card') {
    $options['apply3DSecure'] = $config['apply3DSecure'];
}

// Your own order reference; it must be unique per attempt.
$vendorTxCode = 'DEMO-' . bin2hex(random_bytes(8));

// Optional: a reference for your acquirer's settlement report. Letters and
// digits only, 30 at most. Opayo returns it with the transaction.
$options['settlementReferenceText'] = preg_replace('/[^A-Za-z0-9]/', '', $vendorTxCode);

$request = new CreatePayment(
    $endpoint,
    $auth,
    $paymentMethod,
    $vendorTxCode,
    (new Amount(new Currency('GBP'), 0))->withMajorUnit($_POST['amount'] ?? '9.99'),
    $_POST['description'] ?? 'Demo purchase',
    new Address('88', '88 Avenue Road', 'London', 'EC2A 4DP', 'GB'),
    new Person($_POST['firstName'] ?? 'Sam', $_POST['lastName'] ?? 'Jones', $_POST['email'] ?? 'sam.jones@example.com'),
    options: $options
);

// 4. Send it, and find out what happens next.
$outcome = PaymentOutcome::fromResponse(
    ResponseFactory::fromHttpResponse($client->sendRequest($request))
);

$_SESSION['paymentMethod'] = $method;

// Apple Pay posted by fetch: answer with JSON so the sheet can close with the
// real result, then the page goes to complete.php.
if ($json) {
    $_SESSION['outcome'] = $outcome->summary();
    header('Content-Type: application/json');
    exit(json_encode(['approved' => $outcome->summary()['successful'], 'completeUrl' => 'complete.php']));
}

// 5. Act on the outcome. Identical for every method.
if ($outcome->kind === OutcomeKind::Challenge) {
    // Send the browser to the card issuer, who returns it to notification.php.
    // Record the transaction against your order, and give the issuer your order
    // reference to hand back: the return may arrive without the session cookie.
    rememberTransaction($orderStore, $vendorTxCode, $outcome->transactionId());
    $fields = $outcome->formFields(base64_encode($vendorTxCode));

    pageTop('3D Secure');
    echo '<section class="bg-white rounded-xl shadow p-6 space-y-4">'
        . '<h2 class="text-lg font-semibold text-slate-800">Your card issuer wants to check it is you</h2>'
        . '<form method="post" action="' . h($outcome->acsUrl()) . '">';
    foreach ($fields as $name => $value) {
        echo '<input type="hidden" name="' . h($name) . '" value="' . h($value) . '">';
    }
    // A real site would usually submit this form automatically.
    echo '<button class="w-full bg-blue-600 text-white font-semibold py-2 rounded-lg">Continue to 3D Secure</button>'
        . '</form></section>';
    pageBottom();
    exit;
}

if ($outcome->kind === OutcomeKind::Redirect) {
    // PayPal: the shopper approves there, then comes back to paypal-return.php.
    $_SESSION['transactionId'] = $outcome->transactionId();
    header('Location: ' . $outcome->redirectUrl());
    exit;
}

// Finished or Rejected: show the result (redirect first, so a refresh cannot pay twice).
$_SESSION['outcome'] = $outcome->summary();
header('Location: complete.php');
