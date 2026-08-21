<?php

/**
 * Process the payment.
 *
 * Card capture:
 *  - Opayo JS mode: the form arrives with "card-identifier" and
 *    "merchantSessionKey" already set (the card never touched PHP).
 *  - Server-side mode: the card fields arrive here and are tokenised
 *    with the API before paying.
 *
 * 3D Secure:
 *  - Off: apply3DSecure Disable, single round trip, result shown here.
 *  - On: apply3DSecure Force plus a strongCustomerAuthentication object.
 *    The gateway returns an ACS URL and creq; we show a button that POSTs
 *    the browser to the challenge, which returns to notification.php.
 */

declare(strict_types=1);

require __DIR__ . '/shared.php';

use Academe\Opayo\Pi\Request\CreateCardIdentifier;
use Academe\Opayo\Pi\Request\CreatePayment;
use Academe\Opayo\Pi\Request\Enums\ChallengeWindowSize;
use Academe\Opayo\Pi\Request\Enums\EntryMethod;
use Academe\Opayo\Pi\Request\Enums\TransType;
use Academe\Opayo\Pi\Request\Model\Address;
use Academe\Opayo\Pi\Request\Model\Person;
use Academe\Opayo\Pi\Request\Model\SingleUseCard;
use Academe\Opayo\Pi\Request\Model\StrongCustomerAuthentication;
use Academe\Opayo\Pi\Response\CardIdentifier;
use Academe\Opayo\Pi\Response\ErrorCollection;
use Academe\Opayo\Pi\Response\Secure3Dv2Redirect;
use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Money\Currency;

$use3ds = ! empty($_POST['use3ds']);

// ---------------------------------------------------------------------------
// Step 1: get a session key and card identifier, one way or the other.
// ---------------------------------------------------------------------------

if (! empty($_POST['card-identifier'])) {
    // Opayo JS drop-in mode: tokenised in the browser.
    $sessionKey = $_POST['merchantSessionKey'];
    $cardIdentifier = $_POST['card-identifier'];
    recordWire('Card identifier (tokenised in the browser by sagepay.js)', [
        'merchantSessionKey' => $sessionKey,
        'card-identifier' => $cardIdentifier,
    ]);
} else {
    // Server-side mode: tokenise the posted test card ourselves.
    $sessionKey = createMerchantSessionKey();

    $response = sendAndRecord(new CreateCardIdentifier(
        opayoEndpoint(),
        opayoAuth(),
        $sessionKey,
        $_POST['cardholderName'],
        $_POST['cardNumber'],
        $_POST['cardExpiry'],
        $_POST['cardCvv'] ?: null
    ), 'Card identifier');

    if (! $response instanceof CardIdentifier) {
        pageTop('Card error');
        showErrors($response, 'The card could not be tokenised.');
        pageBottom();
        exit;
    }

    $cardIdentifier = $response->getCardIdentifier();
}

// ---------------------------------------------------------------------------
// Step 2: build and send the payment.
// ---------------------------------------------------------------------------

// Keep under the 40 character vendorTxCode limit.
$vendorTxCode = 'DEMO-' . uniqid() . '-' . time();

$options = [
    'entryMethod' => EntryMethod::Ecommerce,
    'apply3DSecure' => $use3ds
        ? CreatePayment::APPLY_3D_SECURE_FORCE
        : CreatePayment::APPLY_3D_SECURE_DISABLE,
];

if ($use3ds) {
    // The ACS posts the challenge result to notification.php through the
    // shopper's browser, so a localhost URL works.
    $browserIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $options['strongCustomerAuthentication'] = new StrongCustomerAuthentication(
        baseUrl() . '/notification.php',
        str_contains($browserIp, ':') ? '127.0.0.1' : $browserIp, // IPv4 only
        $_SERVER['HTTP_ACCEPT'] ?? '*/*',
        true,
        $_POST['browserLanguage'] ?: 'en-GB',
        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        ChallengeWindowSize::Medium,
        TransType::GoodsAndServicePurchase,
        [
            'browserJavaEnabled' => false,
            'browserColorDepth' => (int)$_POST['browserColorDepth'],
            'browserScreenHeight' => (int)$_POST['browserScreenHeight'],
            'browserScreenWidth' => (int)$_POST['browserScreenWidth'],
            'browserTz' => (int)$_POST['browserTz'],
        ]
    );
}

$request = new CreatePayment(
    opayoEndpoint(),
    opayoAuth(),
    new SingleUseCard($sessionKey, $cardIdentifier),
    $vendorTxCode,
    (new Amount(new Currency('GBP'), 0))->withMajorUnit($_POST['amount']),
    $_POST['description'],
    new Address('88', '88 Avenue Road', 'London', 'EC2A 4DP', 'GB'),
    new Person($_POST['firstName'], $_POST['lastName'], $_POST['email']),
    options: $options
);

$response = sendAndRecord($request, 'Payment');

// ---------------------------------------------------------------------------
// Step 3: act on the response.
// ---------------------------------------------------------------------------

if ($response instanceof Secure3Dv2Redirect) {
    // Remember the transaction for notification.php; the challenge result
    // arrives with no other way to identify it (threeDSSessionData aside).
    $_SESSION['transactionId'] = $response->getTransactionId();
    $_SESSION['vendorTxCode'] = $vendorTxCode;

    // Do not use the transactionId as session data - Opayo rejects it.
    $paFields = $response->getPaRequestFields(base64_encode($vendorTxCode));

    recordWire('3D Secure redirect (browser POSTs this to the ACS)', [
        'acsUrl' => $response->getAcsUrl(),
        'fields' => $paFields,
    ]);

    pageTop('3D Secure challenge');
    ?>
    <div class="bg-white rounded-xl shadow p-6 space-y-4">
        <h2 class="text-lg font-semibold text-amber-600">3D Secure authentication required</h2>
        <p class="text-sm text-slate-600">
            The gateway responded with status <code>3DAuth</code>. Your browser now POSTs the
            <code>creq</code> to the card issuer's Access Control Server (ACS). After the
            challenge, the ACS sends your browser back to <code>notification.php</code>.
        </p>
        <form method="post" action="<?= h($response->getAcsUrl()) ?>">
            <?php foreach ($paFields as $name => $value): ?>
                <input type="hidden" name="<?= h($name) ?>" value="<?= h($value) ?>">
            <?php endforeach; ?>
            <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2 rounded-lg">
                Continue to the 3D Secure challenge
            </button>
        </form>
    </div>
    <?php
    pageBottom();
    exit;
}

pageTop('Payment result');

if ($response instanceof ErrorCollection) {
    showErrors($response, 'The payment request was rejected.');
} else {
    showTransactionResult($response);
}

?>
<a href="index.php" class="inline-block text-sm text-blue-600 hover:underline">&larr; Make another payment</a>
<?php

pageBottom();

// ---------------------------------------------------------------------------

function showErrors(mixed $response, string $heading): void
{
    echo '<div class="bg-white rounded-xl shadow p-6 space-y-3">';
    echo '<h2 class="text-lg font-semibold text-red-600">' . h($heading) . '</h2>';

    if ($response instanceof ErrorCollection) {
        echo '<ul class="text-sm text-slate-700 list-disc pl-5 space-y-1">';
        foreach ($response as $error) {
            $detail = $error->jsonSerialize();
            echo '<li>' . h($detail['description'] ?? 'Unknown error')
                . ' <span class="text-slate-400">(' . h($detail['property'] ?? '-') . ')</span></li>';
        }
        echo '</ul>';
    }

    echo '<a href="index.php" class="inline-block text-sm text-blue-600 hover:underline">&larr; Try again</a>';
    echo '</div>';
}

function showTransactionResult(mixed $response): void
{
    $ok = method_exists($response, 'isSuccessful') && $response->isSuccessful();
    $statusColour = $ok ? 'text-emerald-600' : 'text-red-600';

    echo '<div class="bg-white rounded-xl shadow p-6 space-y-2">';
    echo '<h2 class="text-lg font-semibold ' . $statusColour . '">'
        . ($ok ? 'Payment successful' : 'Payment not authorised') . '</h2>';

    echo '<dl class="text-sm grid grid-cols-[10rem_1fr] gap-y-1">';
    foreach ([
        'Status' => $response->getStatus(),
        'Status detail' => $response->getStatusDetail(),
        'Transaction ID' => $response->getTransactionId(),
        '3D Secure' => $response->get3DSecureStatus(),
    ] as $label => $value) {
        echo '<dt class="text-slate-500">' . h($label) . '</dt><dd class="font-mono">' . h((string)$value) . '</dd>';
    }
    echo '</dl>';

    // The sandbox rejects 3D Secure before any challenge is issued in two
    // easily-hit situations; explain them rather than leave the user guessing.
    if (! $ok && str_contains((string)$response->getStatusDetail(), '3D-Authentication failed')) {
        echo <<<HTML
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
        HTML;
    }

    echo '</div>';
}
