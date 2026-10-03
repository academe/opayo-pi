<?php

/**
 * The one payment endpoint. Every method - card, Google Pay, Apple Pay,
 * PayPal - arrives here and follows the same three steps:
 *
 *   1. Turn the posted credential into a payment method (the ONE place the
 *      method matters).
 *   2. Build and send a single CreatePayment, always with an SCA object.
 *   3. Act on the response type: Opayo decides the next step, not the method.
 *
 * This is the thesis of docs/wallets-handover.md made into code: the token is
 * the boundary; after it, the flow is identical.
 */

declare(strict_types=1);

require __DIR__ . '/shared.php';

use Academe\Opayo\Pi\Request\CreateCardIdentifier;
use Academe\Opayo\Pi\Request\CreatePayment;
use Academe\Opayo\Pi\Request\Enums\EntryMethod;
use Academe\Opayo\Pi\Request\Model\Address;
use Academe\Opayo\Pi\Request\Model\Person;
use Academe\Opayo\Pi\Response\CardIdentifier;
use Academe\Opayo\Pi\Response\ErrorCollection;
use Academe\Opayo\Pi\Response\PayPalRedirect;
use Academe\Opayo\Pi\Response\Secure3Dv2Redirect;
use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Money\Currency;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// The card drop-in tokenised in the browser and posts its own session key.
// Everything else (server-side card, wallets, PayPal) needs one made here.
$sessionKey = $_POST['merchantSessionKey'] ?? null;
if (! $sessionKey) {
    $sessionKey = createMerchantSessionKey();
}

// Server-side card capture: tokenise the posted PAN into a card-identifier
// first, so the factory below sees the same shape the drop-in produces.
if (! empty($_POST['cardNumber']) && empty($_POST['card-identifier'])) {
    $response = sendAndRecord(new CreateCardIdentifier(
        opayoEndpoint(),
        opayoAuth(),
        $sessionKey,
        $_POST['cardholderName'],
        $_POST['cardNumber'],
        $_POST['cardExpiry'],
        ($_POST['cardCvv'] ?? '') ?: null
    ), 'Card identifier');

    if (! $response instanceof CardIdentifier) {
        pageTop('Card error');
        renderErrors($response, 'The card could not be tokenised.');
        pageBottom();
        exit;
    }

    $_POST['card-identifier'] = $response->getCardIdentifier();
}

// Step 1: one credential -> one payment method.
try {
    $paymentMethod = paymentMethodFromRequest($_POST, $sessionKey, $clientIp);
} catch (InvalidArgumentException $e) {
    pageTop('Payment error');
    echo '<div class="bg-white rounded-xl shadow p-6 space-y-3">'
        . '<h2 class="text-lg font-semibold text-red-600">Nothing to pay with</h2>'
        . '<p class="text-sm text-slate-700">' . h($e->getMessage()) . '</p>'
        . '<a href="index.php" class="inline-block text-sm text-blue-600 hover:underline">&larr; Back to checkout</a>'
        . '</div>';
    pageBottom();
    exit;
}

// Step 2: one request for every method. SCA is always sent (a Google PAN_ONLY
// token may still be challenged). apply3DSecure is card-only: forcing it on a
// device-authenticated wallet token would be wrong.
$vendorTxCode = 'DEMO-' . uniqid() . '-' . time();

$options = [
    'entryMethod' => EntryMethod::Ecommerce,
    'strongCustomerAuthentication' => scaFromRequest($_POST, baseUrl() . '/notification.php', $clientIp),
];

if (! empty($_POST['card-identifier']) && ! empty($_POST['use3ds'])) {
    $options['apply3DSecure'] = CreatePayment::APPLY_3D_SECURE_FORCE;
}

$request = new CreatePayment(
    opayoEndpoint(),
    opayoAuth(),
    $paymentMethod,
    $vendorTxCode,
    (new Amount(new Currency('GBP'), 0))->withMajorUnit($_POST['amount'] ?? '9.99'),
    $_POST['description'] ?? 'Demo purchase',
    new Address('88', '88 Avenue Road', 'London', 'EC2A 4DP', 'GB'),
    new Person($_POST['firstName'] ?? 'Sam', $_POST['lastName'] ?? 'Jones', $_POST['email'] ?? 'sam.jones@example.com'),
    options: $options
);

$response = sendAndRecord($request, 'Payment');

// Apple Pay posts by fetch and needs the outcome as JSON so it can complete the
// payment sheet with the real status; it then navigates to result.php.
if (($_POST['resultFormat'] ?? '') === 'json') {
    respondJson($response, $vendorTxCode);
    exit;
}

// Step 3: Opayo decides the next step, not the payment method.
if ($response instanceof Secure3Dv2Redirect) {
    $_SESSION['transactionId'] = $response->getTransactionId();
    $_SESSION['vendorTxCode'] = $vendorTxCode;

    pageTop('3D Secure challenge');
    renderAcsRedirect($response, $vendorTxCode);
    pageBottom();
    exit;
}

if ($response instanceof PayPalRedirect) {
    $_SESSION['paypalTransactionId'] = $response->getTransactionId();
    $_SESSION['vendorTxCode'] = $vendorTxCode;

    pageTop('PayPal redirect');
    renderPayPalRedirect($response);
    pageBottom();
    exit;
}

pageTop('Payment result');
if ($response instanceof ErrorCollection) {
    renderErrors($response, 'The payment request was rejected.');
} else {
    renderResult($response);
    echo '<a href="index.php" class="inline-block text-sm text-blue-600 hover:underline">&larr; Back to checkout</a>';
}
pageBottom();
