<?php

/**
 * PayPal return. After the shopper approves (or cancels) at PayPal, Opayo
 * sends their browser here with the transactionId on the URL. The URL says
 * nothing about the result, so fetch the transaction from Opayo. Before you
 * fulfil the order, also check the fetched transaction matches it (at least
 * the amount).
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Academe\Opayo\Pi\Checkout\PaymentOutcome;
use Academe\Opayo\Pi\Factory\ResponseFactory;
use Academe\Opayo\Pi\Request\FetchTransaction;

if (! in_array('paypal', $enabledMethods, true)) {
    http_response_code(400);
    exit('PayPal is not offered.');
}

// Trust the transaction you recorded for this shopper's order, not the URL:
// anyone can edit a query string. The one on the URL must match it.
$transactionId = $_SESSION['transactionId'] ?? null;

if ($transactionId === null || (isset($_GET['transactionId']) && $_GET['transactionId'] !== $transactionId)) {
    http_response_code(400);
    pageTop('PayPal');
    echo placeholder(
        'This PayPal return does not match your order',
        'The transaction on the return URL is not the one started for this order.'
    );
    pageBottom();
    exit;
}

$response = ResponseFactory::fromHttpResponse($client->sendRequest(
    new FetchTransaction($endpoint, $auth, $transactionId)
));

$_SESSION['outcome'] = PaymentOutcome::fromResponse($response)->summary();
header('Location: complete.php');
