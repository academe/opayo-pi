<?php

/**
 * PayPal: register the transaction and hand the browser to PayPal.
 *
 * Flow (Opayo "PayPal integration" guide):
 *  1. Create a merchant session key.
 *  2. POST /transactions with paymentMethod.paypal = {merchantSessionKey, callbackUrl}.
 *  3. Opayo answers status "Redirect" (statusCode 2023) with
 *     paymentMethod.paypal.redirectUrl - send the shopper there, full page.
 *  4. After PayPal, Opayo redirects the shopper's browser to callbackUrl
 *     (paypal-return.php) with the transactionId appended; that page fetches
 *     the transaction for the final outcome.
 *
 * Only vendors with PayPal enabled in MyOpayo can do this; of the sandbox
 * profiles, the public "sandbox" vendor has it, "sandboxEC" and personal
 * test accounts do not ("Vendor not enrolled with this wallet type").
 */

declare(strict_types=1);

require __DIR__ . '/shared.php';

use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Money\Currency;
use Academe\Opayo\Pi\Request\CreatePayment;
use Academe\Opayo\Pi\Request\Enums\EntryMethod;
use Academe\Opayo\Pi\Request\Model\Address;
use Academe\Opayo\Pi\Request\Model\PayPalPayment;
use Academe\Opayo\Pi\Request\Model\Person;
use Academe\Opayo\Pi\Response\PayPalRedirect;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?paypal=1');
    exit;
}

// Step 1: a merchant session key; PayPal needs one even though no card is tokenised.
$sessionKey = createMerchantSessionKey();

// Step 2: the payment, with PayPal as the payment method.
$vendorTxCode = 'DEMO-PP-' . uniqid() . '-' . time();

$request = new CreatePayment(
    opayoEndpoint(),
    opayoAuth(),
    new PayPalPayment($sessionKey, baseUrl() . '/paypal-return.php'),
    $vendorTxCode,
    (new Amount(new Currency('GBP'), 0))->withMajorUnit($_POST['amount']),
    $_POST['description'],
    new Address('88', '88 Avenue Road', 'London', 'EC2A 4DP', 'GB'),
    new Person($_POST['firstName'], $_POST['lastName'], $_POST['email']),
    options: ['entryMethod' => EntryMethod::Ecommerce]
);

$response = sendAndRecord($request, 'PayPal payment registration');

// Step 3: act on the response.

if ($response instanceof PayPalRedirect) {
    // Remember the transaction so paypal-return.php can cross-check the
    // transactionId Opayo appends to the callback URL.
    $_SESSION['paypalTransactionId'] = $response->getTransactionId();
    $_SESSION['vendorTxCode'] = $vendorTxCode;

    pageTop('PayPal redirect');
    ?>
    <div class="bg-white rounded-xl shadow p-6 space-y-4">
        <h2 class="text-lg font-semibold text-amber-600">Redirect to PayPal</h2>
        <p class="text-sm text-slate-700">
            The gateway responded with status <code>Redirect</code> (2023): the transaction is
            registered and the shopper must approve it at PayPal. This must be a full-page
            redirect (PayPal refuses to run in an iframe). PayPal order ID
            <code><?= h($response->getOrderId()) ?></code>.
        </p>
        <p class="text-sm text-slate-700">
            At the PayPal <em>sandbox</em> you need a PayPal sandbox <strong>buyer</strong> login
            (free: developer.paypal.com &rarr; Sandbox &rarr; Accounts). After approving, PayPal hands
            back to Opayo, which redirects you to <code><?= h(baseUrl()) ?>/paypal-return.php</code>.
        </p>
        <a href="<?= h($response->getRedirectUrl()) ?>"
           class="inline-block bg-blue-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-blue-700">
            Continue to PayPal
        </a>
        <p class="text-xs text-slate-500">
            Or, to see the callback handling without a PayPal login, open
            <a class="text-blue-600 hover:underline" href="paypal-return.php?transactionId=<?= h($response->getTransactionId()) ?>">paypal-return.php
            with this transactionId</a>. Until PayPal reports back, Opayo answers
            <code>404 Transaction not found</code> for it - a PayPal transaction only becomes
            fetchable once the shopper has finished at PayPal.
        </p>
    </div>
    <?php
    pageBottom();
    exit;
}

pageTop('PayPal error');
showErrors($response, 'PayPal registration failed.');
pageBottom();

// ---------------------------------------------------------------------------

function showErrors(mixed $response, string $heading): void
{
    echo '<div class="bg-white rounded-xl shadow p-6 space-y-3">';
    echo '<h2 class="text-lg font-semibold text-red-600">' . h($heading) . '</h2>';

    if ($response instanceof \Academe\Opayo\Pi\Response\ErrorCollection) {
        echo '<ul class="text-sm text-slate-700 list-disc pl-5 space-y-1">';
        foreach ($response as $error) {
            $detail = $error->jsonSerialize();
            echo '<li>' . h($detail['description'] ?? 'Unknown error')
                . ' <span class="text-slate-400">(' . h($detail['property'] ?? ($detail['code'] ?? '-')) . ')</span></li>';
        }
        echo '</ul>';

        echo '<p class="text-xs text-slate-500">"Vendor not enrolled with this wallet type" means PayPal is not '
            . 'enabled on this vendor. Switch the account selector to the public sandbox, which has it.</p>';
    } elseif (method_exists($response, 'getStatusDetail')) {
        echo '<p class="text-sm text-slate-700">' . h((string)$response->getStatus()) . ' - '
            . h((string)$response->getStatusDetail()) . '</p>';
    }

    echo '<a href="index.php?paypal=1" class="inline-block text-sm text-blue-600 hover:underline">&larr; Try again</a>';
    echo '</div>';
}
