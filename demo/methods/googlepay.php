<?php

/**
 * Google Pay panel.
 *
 * The request objects are built server-side by the library
 * (Academe\Opayo\Pi\GooglePay\Configuration) and handed to the browser as
 * OPAYO_GOOGLE_PAY. The sheet mints a token; this panel posts it to pay.php as
 * googlePayToken, and pay.php turns it into paymentMethod.googlePay.
 *
 * Readiness has no Opayo pre-flight: the browser (isReadyToPay) decides, and
 * enrolment is only proven by paying. The two knobs (environment,
 * gatewayMerchantId) are demo aids, not part of a real integration.
 *
 * Expects from index.php scope: $order (array).
 *
 * @var array<string,string> $order
 */

use Academe\Opayo\Pi\GooglePay\Configuration as GooglePayConfiguration;
use Academe\Opayo\Pi\GooglePay\Environment as GooglePayEnvironment;
use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Money\Currency;

$googlePay = googlePayReadiness();
?>
<section class="bg-white rounded-xl shadow p-6 space-y-4">
    <h2 class="text-lg font-semibold text-slate-800">Google Pay</h2>

    <?php if (! $googlePay['available']): ?>
        <?= methodPlaceholder($googlePay) ?>
    <?php else: ?>
        <?php
        // The client config the library produces for this order. In a real
        // integration you would use this verbatim.
        $googlePayClientConfig = (new GooglePayConfiguration(
            gatewayMerchantId: googlePayMerchantId(),
            merchantName: 'Opayo Pi Demo',
            googleMerchantId: googlePayGoogleMerchantId() ?: null,
            environment: GooglePayEnvironment::Test,
        ))->clientConfiguration(
            (new Amount(new Currency('GBP'), 0))->withMajorUnit($order['amount'])
        );
        ?>
        <form id="googlepay-form" method="post" action="pay.php" class="space-y-4">
            <?= orderHiddenInputs($order) ?>
            <?= browserHiddenInputs() ?>
            <!-- Filled in by the Google Pay sheet, just before this form submits. -->
            <input type="hidden" name="googlePayToken" id="googlePayToken">

            <!-- Demo knobs: TEST always yields the placeholder token; PRODUCTION
                 needs a Google merchant ID and an allowlisted origin. -->
            <div class="grid grid-cols-2 gap-4">
                <label class="block text-sm">
                    <span class="text-slate-600">Google Pay environment</span>
                    <select id="gp-environment" class="mt-1 w-full rounded border-slate-300">
                        <option value="TEST">TEST (placeholder token)</option>
                        <option value="PRODUCTION">PRODUCTION (real token)</option>
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">gatewayMerchantId</span>
                    <input type="text" id="gp-gateway-merchant-id" value="<?= h(googlePayMerchantId()) ?>" class="mt-1 w-full rounded border-slate-300 font-mono text-xs">
                </label>
            </div>

            <div id="googlepay-button" class="min-h-[44px]"></div>
            <p id="googlepay-status" class="text-xs text-red-600 hidden"></p>
            <p class="text-xs text-slate-500"><?= h($googlePay['detail']) ?></p>
        </form>

        <script src="https://pay.google.com/gp/p/js/pay.js"></script>
        <script>
        (function () {
            const OPAYO_GOOGLE_PAY = <?= json_encode($googlePayClientConfig) ?>;
            const GPAY_GOOGLE_MERCHANT_ID = <?= json_encode(googlePayGoogleMerchantId()) ?>;
            const form = document.getElementById('googlepay-form');

            const gpStatus = (message) => {
                const el = document.getElementById('googlepay-status');
                el.textContent = message;
                el.classList.toggle('hidden', ! message);
            };

            // The server config with the demo's live values applied. Amount comes
            // from the shared order fields, not a panel-local input.
            function gpRequests() {
                const config = structuredClone(OPAYO_GOOGLE_PAY);
                const environment = document.getElementById('gp-environment').value;

                config.paymentDataRequest.allowedPaymentMethods[0].tokenizationSpecification
                    .parameters.gatewayMerchantId = document.getElementById('gp-gateway-merchant-id').value.trim();
                config.paymentDataRequest.transactionInfo.totalPrice =
                    form.querySelector('[data-order="amount"]').value.trim();

                if (environment === 'PRODUCTION') {
                    config.paymentDataRequest.merchantInfo.merchantId = GPAY_GOOGLE_MERCHANT_ID;
                }
                config.environment = environment;
                return config;
            }

            // A PaymentsClient is bound to its environment, so switching the
            // select rebuilds both the client and the button it drew.
            function gpRenderButton() {
                const config = gpRequests();
                const client = new google.payments.api.PaymentsClient({ environment: config.environment });
                const container = document.getElementById('googlepay-button');

                client.isReadyToPay(config.isReadyToPayRequest)
                    .then((response) => {
                        container.replaceChildren();
                        if (! response.result) {
                            gpStatus('Google Pay is not available in this browser (no Google account, or an unsupported browser).');
                            return;
                        }
                        gpStatus('');
                        container.appendChild(client.createButton({
                            buttonType: 'pay',
                            buttonSizeMode: 'fill',
                            onClick: () => gpPay(client),
                        }));
                    })
                    .catch((error) => gpStatus('isReadyToPay failed: ' + error));
            }

            function gpPay(client) {
                client.loadPaymentData(gpRequests().paymentDataRequest).then((paymentData) => {
                    // Real device values for the 3D Secure profile.
                    const depth = [1, 4, 8, 15, 16, 24, 32].includes(screen.colorDepth) ? screen.colorDepth : 24;
                    form.querySelector('[name="browserColorDepth"]').value = depth;
                    form.querySelector('[name="browserScreenHeight"]').value = screen.height;
                    form.querySelector('[name="browserScreenWidth"]').value = screen.width;
                    form.querySelector('[name="browserTz"]').value = new Date().getTimezoneOffset();
                    form.querySelector('[name="browserLanguage"]').value = navigator.language || 'en-GB';

                    // Post the whole token string; pay.php base64-encodes it.
                    document.getElementById('googlePayToken').value = paymentData.paymentMethodData.tokenizationData.token;
                    form.submit();
                }).catch((error) => {
                    if (error.statusCode === 'CANCELED') { gpStatus(''); return; }
                    gpStatus('Google Pay sheet: ' + (error.statusMessage || error.statusCode || error));
                });
            }

            document.getElementById('gp-environment').addEventListener('change', gpRenderButton);
            gpRenderButton();
        })();
        </script>
    <?php endif; ?>
</section>
