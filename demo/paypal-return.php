<?php

/**
 * PayPal callback (the callbackUrl given in paypal.php).
 *
 * After the shopper approves (or cancels) at PayPal, PayPal reports back to
 * Opayo, and Opayo redirects the shopper's BROWSER here with the Opayo
 * transactionId appended as a query parameter. Nothing about the outcome is
 * in the URL: the transaction must be fetched from Opayo to see whether it
 * was authorised.
 */

declare(strict_types=1);

require __DIR__ . '/shared.php';

use Academe\Opayo\Pi\Request\FetchTransaction;
use Academe\Opayo\Pi\Response\ErrorCollection;

recordWire('Callback query string (as redirected by Opayo)', $_GET);

$transactionId = $_GET['transactionId'] ?? $_GET['transactionid'] ?? $_SESSION['paypalTransactionId'] ?? null;

if ($transactionId === null) {
    pageTop('PayPal return');
    echo '<div class="bg-white rounded-xl shadow p-6 text-sm text-red-600">'
        . 'No transactionId on the callback URL and none in the session; cannot look up the transaction. '
        . '<a class="text-blue-600 hover:underline" href="index.php?paypal=1">Start again</a>.</div>';
    pageBottom();
    exit;
}

if (isset($_SESSION['paypalTransactionId']) && $_SESSION['paypalTransactionId'] !== $transactionId) {
    recordWire('Warning', 'transactionId on the callback differs from the one registered in this session.');
}

$response = sendAndRecord(
    new FetchTransaction(opayoEndpoint(), opayoAuth(), $transactionId),
    'Fetch transaction'
);

unset($_SESSION['paypalTransactionId']);

pageTop('PayPal result');

if ($response instanceof ErrorCollection) {
    $notFound = $response->getHttpCode() === 404;

    echo '<div class="bg-white rounded-xl shadow p-6 space-y-3">';
    echo '<h2 class="text-lg font-semibold ' . ($notFound ? 'text-amber-600' : 'text-red-600') . '">'
        . ($notFound ? 'Transaction not available yet' : 'Could not fetch the transaction') . '</h2><ul class="text-sm list-disc pl-5">';
    foreach ($response as $error) {
        $detail = $error->jsonSerialize();
        echo '<li>' . h($detail['description'] ?? 'Unknown error') . '</li>';
    }
    echo '</ul>';
    if ($notFound) {
        echo '<p class="text-xs text-slate-500">Opayo answers <code>404 Transaction not found</code> for a PayPal '
            . 'transaction until PayPal has reported the outcome back (approved or cancelled). If you came here '
            . 'directly from the redirect page without finishing at PayPal, this is expected; a real callback from '
            . 'Opayo only happens after PayPal reports back, so in production this page should not see 404s.</p>';
    }
    echo '</div>';
} else {
    $ok = method_exists($response, 'isSuccessful') && $response->isSuccessful();
    $status = (string)$response->getStatus();

    echo '<div class="bg-white rounded-xl shadow p-6 space-y-2">';
    echo '<h2 class="text-lg font-semibold ' . ($ok ? 'text-emerald-600' : ($status === 'Redirect' ? 'text-amber-600' : 'text-red-600')) . '">'
        . ($ok ? 'Payment successful (PayPal)' : ($status === 'Redirect' ? 'Awaiting PayPal approval' : 'Payment not authorised'))
        . '</h2>';
    echo '<dl class="text-sm grid grid-cols-[10rem_1fr] gap-y-1">';
    foreach ([
        'Status' => $status,
        'Status detail' => $response->getStatusDetail(),
        'Transaction ID' => $response->getTransactionId(),
        'Transaction type' => $response->getTransactionType(),
    ] as $label => $value) {
        echo '<dt class="text-slate-500">' . h($label) . '</dt><dd class="font-mono">' . h((string)$value) . '</dd>';
    }
    echo '</dl>';

    if ($status === 'Redirect') {
        echo '<p class="text-xs text-slate-500">The shopper has not approved the payment at PayPal yet (or you came '
            . 'here directly). The transaction stays in this state until PayPal reports back to Opayo.</p>';
    }
    echo '</div>';
}
?>
<a href="index.php?paypal=1" class="inline-block text-sm text-blue-600 hover:underline">&larr; Make another PayPal payment</a>
<?php
pageBottom();
