<?php

/**
 * 3D Secure return. After the challenge, the card issuer sends the shopper's
 * browser here with the result ("cres") and your order reference
 * ("threeDSSessionData", as pay.php gave it). Look up the transaction for that
 * order, forward the result to Opayo to finish the payment, then show the
 * result like any other.
 *
 * Do not rely on the session here: this is a cross-site POST from the issuer,
 * and browsers may not send the session cookie with it.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Academe\Opayo\Pi\Checkout\PaymentOutcome;
use Academe\Opayo\Pi\Factory\ResponseFactory;
use Academe\Opayo\Pi\Request\CreateSecure3Dv2Challenge;
use Academe\Opayo\Pi\ServerRequest\Secure3Dv2Notification;

if (! Secure3Dv2Notification::isRequest($_POST)) {
    // Opened directly.
    header('Location: checkout.php');
    exit;
}

$orderRef = base64_decode((string) ($_POST['threeDSSessionData'] ?? ''), true);
$transactionId = $orderRef !== false ? recallTransaction($orderStore, $orderRef) : null;

if ($transactionId === null) {
    http_response_code(400);
    pageTop('3D Secure');
    echo placeholder(
        'The 3D Secure result could not be matched to an order',
        'No transaction is recorded for the order reference the card issuer returned.'
    );
    pageBottom();
    exit;
}

$response = ResponseFactory::fromHttpResponse($client->sendRequest(
    new CreateSecure3Dv2Challenge($endpoint, $auth, Secure3Dv2Notification::fromData($_POST), $transactionId)
));

$_SESSION['outcome'] = PaymentOutcome::fromResponse($response)->summary();
header('Location: complete.php');
