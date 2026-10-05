<?php

/**
 * Google Pay.
 *
 * The package builds the Google Pay request objects (GooglePay\Configuration);
 * this page hands them to Google's pay.js. Google's sheet returns a token,
 * which is posted to pay.php as googlePayToken. Google encrypts the card to
 * Opayo's key, so the token is opaque to you.
 *
 * Back end: $config['googlePayMerchantId'] (gatewayMerchantId, from MyOpayo),
 * $config['googlePayEnvironment'], and in PRODUCTION $config['googleMerchantId'].
 * Extra endpoint: none.
 *
 * From checkout.php: $config, $order.
 */

use Academe\Opayo\Pi\GooglePay\Configuration as GooglePayConfiguration;
use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Money\Currency;

try {
    $googlePay = (new GooglePayConfiguration(
        gatewayMerchantId: $config['googlePayMerchantId'],
        merchantName: $config['merchantName'],
        googleMerchantId: $config['googleMerchantId'],
        environment: $config['googlePayEnvironment'],
    ))->clientConfiguration((new Amount(new Currency('GBP'), 0))->withMajorUnit($order['amount']));
    $googlePayError = null;
} catch (InvalidArgumentException $e) {
    $googlePay = null;
    $googlePayError = $e->getMessage();
}
?>
<section class="bg-white rounded-xl shadow p-6 space-y-4">
    <h2 class="text-lg font-semibold text-slate-800">Google Pay</h2>

    <?php if ($googlePay === null): ?>
        <?= placeholder('Google Pay is misconfigured', (string) $googlePayError) ?>
    <?php else: ?>
        <form id="googlepay-form" method="post" action="pay.php" data-pay>
            <?= orderFields($order) ?>
            <input type="hidden" name="googlePayToken">
            <div id="googlepay-button" class="min-h-[44px]"></div>
            <p id="googlepay-status" class="text-xs text-slate-500"></p>
        </form>

        <script src="https://pay.google.com/gp/p/js/pay.js"></script>
        <script>
        (function () {
            const config = <?= json_encode($googlePay) ?>;
            const form = document.getElementById('googlepay-form');
            const status = (message) => { document.getElementById('googlepay-status').textContent = message; };
            const client = new google.payments.api.PaymentsClient({ environment: config.environment });

            // Ask Google whether this browser can pay at all; only then show the button.
            client.isReadyToPay(config.isReadyToPayRequest).then((response) => {
                if (! response.result) {
                    status('Google Pay is not available in this browser.');
                    return;
                }
                document.getElementById('googlepay-button').appendChild(client.createButton({
                    buttonType: 'pay',
                    buttonSizeMode: 'fill',
                    onClick: pay,
                }));
            }).catch((error) => status('Google Pay check failed: ' + error));

            function pay() {
                const request = structuredClone(config.paymentDataRequest);
                request.transactionInfo.totalPrice = form.querySelector('[data-order="amount"]').value.trim();

                client.loadPaymentData(request).then((paymentData) => {
                    form.elements.googlePayToken.value = paymentData.paymentMethodData.tokenizationData.token;
                    form.submit();
                }).catch((error) => {
                    if (error.statusCode !== 'CANCELED') {
                        status('Google Pay: ' + (error.statusMessage || error.statusCode || error));
                    }
                });
            }
        })();
        </script>
    <?php endif; ?>
</section>
