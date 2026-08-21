<?php

/**
 * 3D Secure v2 notification handler.
 *
 * After the challenge, the card issuer's ACS sends the shopper's browser
 * back here with a POST containing the challenge result ("cres"). This is
 * a browser hop, not a server-to-server call, which is why localhost works.
 *
 * The cres is forwarded to Opayo along with the transactionId (stored in
 * the PHP session by pay.php) to complete the authorisation.
 */

declare(strict_types=1);

require __DIR__ . '/shared.php';

use Academe\Opayo\Pi\Request\CreateSecure3Dv2Challenge;
use Academe\Opayo\Pi\ServerRequest\Secure3Dv2Notification;
use Academe\Opayo\Pi\Response\ErrorCollection;

if (! Secure3Dv2Notification::isRequest($_POST)) {
    pageTop('Notification');
    echo '<div class="bg-white rounded-xl shadow p-6 text-sm text-slate-600">'
        . 'No 3D Secure result was posted here. Start a payment from '
        . '<a href="index.php" class="text-blue-600 hover:underline">the payment form</a>.</div>';
    pageBottom();
    exit;
}

$notification = Secure3Dv2Notification::fromData($_POST);

recordWire('ACS notification (POSTed by your browser)', [
    'cres' => substr((string)$notification->getCRes(), 0, 60) . '... (truncated)',
    'threeDSSessionData (decoded)' => base64_decode((string)$notification->getThreeDSSessionData()),
]);

$transactionId = $_SESSION['transactionId'] ?? null;

if ($transactionId === null) {
    pageTop('Notification');
    echo '<div class="bg-white rounded-xl shadow p-6 text-sm text-red-600">'
        . 'No transaction ID in the session; cannot complete the challenge.</div>';
    pageBottom();
    exit;
}

// Forward the challenge result to Opayo to complete the transaction.
$response = sendAndRecord(
    new CreateSecure3Dv2Challenge(opayoEndpoint(), opayoAuth(), $notification, $transactionId),
    '3D Secure challenge completion'
);

unset($_SESSION['transactionId']);

pageTop('3D Secure result');

if ($response instanceof ErrorCollection) {
    echo '<div class="bg-white rounded-xl shadow p-6 space-y-3">';
    echo '<h2 class="text-lg font-semibold text-red-600">Challenge completion failed</h2>';
    echo '<ul class="text-sm text-slate-700 list-disc pl-5 space-y-1">';
    foreach ($response as $error) {
        $detail = $error->jsonSerialize();
        echo '<li>' . h($detail['description'] ?? 'Unknown error') . '</li>';
    }
    echo '</ul></div>';
} else {
    $ok = method_exists($response, 'isSuccessful') && $response->isSuccessful();
    $colour = $ok ? 'text-emerald-600' : 'text-red-600';

    echo '<div class="bg-white rounded-xl shadow p-6 space-y-2">';
    echo '<h2 class="text-lg font-semibold ' . $colour . '">'
        . ($ok ? 'Payment successful (3D Secure authenticated)' : 'Payment not authorised') . '</h2>';
    echo '<dl class="text-sm grid grid-cols-[10rem_1fr] gap-y-1">';
    foreach ([
        'Status' => $response->getStatus(),
        'Status detail' => $response->getStatusDetail(),
        'Transaction ID' => $response->getTransactionId(),
        '3D Secure' => $response->get3DSecureStatus(),
    ] as $label => $value) {
        echo '<dt class="text-slate-500">' . h($label) . '</dt><dd class="font-mono">' . h((string)$value) . '</dd>';
    }
    echo '</dl></div>';
}

?>
<a href="index.php" class="inline-block text-sm text-blue-600 hover:underline">&larr; Make another payment</a>
<?php

pageBottom();
