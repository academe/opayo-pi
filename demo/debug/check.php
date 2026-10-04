<?php

/**
 * Setup check: asks Opayo, once, whether each method can work for this
 * account. Demo only. Google Pay and PayPal are probed by starting a payment
 * that cannot complete, so they only run against the test endpoint.
 */

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

if (! function_exists('debugRecord')) {
    http_response_code(404);
    exit('The debug layer is off (DEMO_DEBUG=0).');
}

use Academe\Opayo\Pi\Checkout\BrowserData;
use Academe\Opayo\Pi\Checkout\PaymentOutcome;
use Academe\Opayo\Pi\Factory\ResponseFactory;
use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Money\Currency;
use Academe\Opayo\Pi\Request\CreateApplePaySession;
use Academe\Opayo\Pi\Request\CreatePayment;
use Academe\Opayo\Pi\Request\Enums\EntryMethod;
use Academe\Opayo\Pi\Request\Model\Address;
use Academe\Opayo\Pi\Request\Model\GooglePayPayment;
use Academe\Opayo\Pi\Request\Model\PayPalPayment;
use Academe\Opayo\Pi\Request\Model\PaymentMethodInterface;
use Academe\Opayo\Pi\Request\Model\Person;
use Academe\Opayo\Pi\Response\ApplePaySession;
use Academe\Opayo\Pi\Response\ErrorCollection;

$results = [];

$send = static fn ($request) => ResponseFactory::fromHttpResponse($client->sendRequest($request));

$probePayment = static function (PaymentMethodInterface $paymentMethod) use ($endpoint, $auth, $baseUrl, $send): PaymentOutcome {
    return PaymentOutcome::fromResponse($send(new CreatePayment(
        $endpoint,
        $auth,
        $paymentMethod,
        'CHECK-' . bin2hex(random_bytes(6)),
        (new Amount(new Currency('GBP'), 0))->withMajorUnit('1.00'),
        'Setup check',
        new Address('1', '1 Test Street', 'London', 'EC2A 4DP', 'GB'),
        new Person('Setup', 'Check', 'check@example.com'),
        options: [
            'entryMethod' => EntryMethod::Ecommerce,
            'strongCustomerAuthentication' => BrowserData::fromArray([])
                ->toStrongCustomerAuthentication($baseUrl . '/notification.php', '127.0.0.1', 'text/html', 'Setup check'),
        ]
    )));
};

$firstCode = static fn (PaymentOutcome $o): string|int|null => $o->isRejected() ? ($o->errors()[0]['code'] ?? null) : null;

// Credentials: every method needs a merchant session key.
try {
    $sessionKey = merchantSessionKey($client, $endpoint, $auth);
    $results[] = ['Credentials (merchant session key)', true, null, 'Opayo accepted your integration key and password.'];
} catch (RuntimeException $e) {
    $sessionKey = null;
    $results[] = ['Credentials (merchant session key)', false, null, $e->getMessage()];
}

// Apple Pay: can Opayo open a merchant session for this domain?
$apple = $send(new CreateApplePaySession($endpoint, $auth, $config['applePayDomain']));
if ($apple instanceof ApplePaySession && $apple->getMerchantSession()) {
    $results[] = ['Apple Pay for ' . $config['applePayDomain'], true, null, 'Opayo opened a merchant session.'];
} else {
    $code = null;
    if ($apple instanceof ErrorCollection) {
        foreach ($apple as $error) {
            $code = $error->getCode();
            break;
        }
    }
    $results[] = ['Apple Pay for ' . $config['applePayDomain'], false, $code, debugExplain($code)];
}

if ($sessionKey !== null && $endpoint->isTesting()) {
    // Google Pay: a dummy token, only to ask whether the wallet is switched on.
    // 6203 means enabled (Opayo got as far as the payload); 6401 means not
    // enabled for this vendor.
    $google = $probePayment(GooglePayPayment::fromGoogleToken($sessionKey, '127.0.0.1', 'examplePaymentMethodToken'));
    $code = $firstCode($google);
    $results[] = ['Google Pay enabled', (string) $code === '6203', $code, debugExplain($code)];

    // PayPal: a Redirect means enabled; 1030 means not.
    $payPal = $probePayment(new PayPalPayment(merchantSessionKey($client, $endpoint, $auth), $baseUrl . '/paypal-return.php'));
    $code = $firstCode($payPal);
    $results[] = ['PayPal enabled', $payPal->isRedirect(), $code, $payPal->isRedirect()
        ? 'Opayo registered a PayPal payment (left unfinished).'
        : debugExplain($code)];
} else {
    $results[] = ['Google Pay and PayPal', false, null, 'Only probed against the test endpoint (they start a payment).'];
}

pageTop('Setup check');
?>
<section class="bg-white rounded-xl shadow p-6 space-y-4">
    <h2 class="text-lg font-semibold text-slate-800">Setup check (<?= $endpoint->isTesting() ? 'test' : 'live' ?>)</h2>
    <ul class="space-y-3 text-sm">
        <?php foreach ($results as [$name, $ok, $code, $detail]): ?>
            <li class="border-l-4 pl-3 <?= $ok ? 'border-emerald-500' : 'border-amber-500' ?>">
                <div class="font-medium"><?= $ok ? 'OK' : 'Not available' ?>: <?= h($name) ?><?= $code !== null ? ' (' . h((string) $code) . ')' : '' ?></div>
                <div class="text-slate-600"><?= h($detail) ?></div>
            </li>
        <?php endforeach; ?>
    </ul>
    <a href="../checkout.php" class="inline-block text-sm text-blue-600 hover:underline">&larr; Back to checkout</a>
</section>
<?php
pageBottom();
