<?php

/**
 * Apple Pay panel (Opayo-managed certificate path).
 *
 * Readiness is a real Opayo call, already made by applePayReadiness(): on
 * 127.0.0.1 or an unregistered domain it returns the gateway's own error
 * (6125 / 6118), so this panel shows a placeholder here. On a registered
 * HTTPS domain in Safari the button appears.
 *
 * The browser half below (ApplePaySession) is written from Apple's and Opayo's
 * documentation and is UNVERIFIED until run on Safari/iOS against a registered
 * domain - see demo/README.md "Verify Apple Pay on your iPhone". The server
 * half (apple-session.php) is verified live.
 *
 * Flow: onvalidatemerchant -> fetch apple-session.php -> completeMerchantValidation;
 * onpaymentauthorized -> post payment.token + sessionValidationToken to pay.php.
 *
 * Expects from index.php scope: $order (array).
 *
 * @var array<string,string> $order
 */

$applePay = applePayReadiness();
?>
<section class="bg-white rounded-xl shadow p-6 space-y-4">
    <h2 class="text-lg font-semibold text-slate-800">Apple Pay</h2>

    <?php if (! $applePay['available']): ?>
        <?= methodPlaceholder($applePay) ?>
    <?php else: ?>
        <form id="applepay-form" method="post" action="pay.php" class="space-y-4">
            <?= orderHiddenInputs($order) ?>
            <?= browserHiddenInputs() ?>
            <input type="hidden" name="applePayToken" id="applePayToken">
            <input type="hidden" name="appleSessionValidationToken" id="appleSessionValidationToken">
            <!-- Apple pays by fetch, so pay.php answers with JSON and the sheet
                 completes with the real outcome before result.php is shown. -->
            <input type="hidden" name="resultFormat" value="json">

            <div id="apple-pay-button"
                 style="-apple-pay-button-style: black; height: 44px;"
                 class="apple-pay-button"></div>
            <p id="applepay-status" class="text-xs text-red-600 hidden"></p>
            <p class="text-xs text-slate-500"><?= h($applePay['detail']) ?></p>
        </form>

        <script>
        // UNVERIFIED browser half: written from Apple + Opayo docs, not yet run
        // on Safari/iOS against a registered domain. The server half
        // (apple-session.php) is verified. See README "Verify Apple Pay".
        (function () {
            const form = document.getElementById('applepay-form');
            const button = document.getElementById('apple-pay-button');
            const account = <?= json_encode(demoAccount()) ?>;

            const status = (message) => {
                const el = document.getElementById('applepay-status');
                el.textContent = message;
                el.classList.toggle('hidden', ! message);
            };

            // Opayo's Apple Pay guide uses version 6; feature-detect so we do
            // not construct a session the browser cannot support.
            const APPLE_PAY_VERSION = 6;
            if (! window.ApplePaySession
                || ! ApplePaySession.supportsVersion(APPLE_PAY_VERSION)
                || ! ApplePaySession.canMakePayments()) {
                button.classList.add('hidden');
                status('Apple Pay needs Safari on an Apple device signed into an Apple ID.');
                return;
            }

            button.addEventListener('click', function () {
                const request = {
                    countryCode: 'GB',
                    currencyCode: 'GBP',
                    merchantCapabilities: ['supports3DS'],
                    supportedNetworks: ['visa', 'masterCard', 'amex', 'discover'],
                    total: {
                        label: 'Opayo Pi Demo',
                        amount: form.querySelector('[data-order="amount"]').value.trim(),
                    },
                };
                const session = new ApplePaySession(APPLE_PAY_VERSION, request);
                let sessionValidationToken = null;

                // The merchant validation call goes to OUR server, which asks
                // Opayo to open the session (Opayo-managed certificate).
                session.onvalidatemerchant = function (event) {
                    fetch('apple-session.php?account=' + encodeURIComponent(account), { method: 'POST' })
                        .then((r) => r.json())
                        .then((data) => {
                            if (data.error) {
                                status('Merchant validation failed: ' + data.error);
                                session.abort();
                                return;
                            }
                            sessionValidationToken = data.sessionValidationToken;
                            session.completeMerchantValidation(data.merchantSession);
                        })
                        .catch((e) => { status('Merchant validation error: ' + e); session.abort(); });
                };

                session.onpaymentauthorized = function (event) {
                    // Post Apple's payment.token object and the validation token;
                    // pay.php builds ApplePayPayment from them.
                    document.getElementById('applePayToken').value = JSON.stringify(event.payment.token);
                    document.getElementById('appleSessionValidationToken').value = sessionValidationToken || '';

                    const depth = [1, 4, 8, 15, 16, 24, 32].includes(screen.colorDepth) ? screen.colorDepth : 24;
                    form.querySelector('[name="browserColorDepth"]').value = depth;
                    form.querySelector('[name="browserScreenHeight"]').value = screen.height;
                    form.querySelector('[name="browserScreenWidth"]').value = screen.width;
                    form.querySelector('[name="browserTz"]').value = new Date().getTimezoneOffset();
                    form.querySelector('[name="browserLanguage"]').value = navigator.language || 'en-GB';

                    // Pay by fetch so the sheet can complete with the REAL outcome
                    // before we leave the page (Apple requires completePayment to
                    // be called inside this handler).
                    fetch('pay.php', { method: 'POST', body: new FormData(form) })
                        .then((r) => r.json())
                        .then((result) => {
                            session.completePayment(result.approved
                                ? ApplePaySession.STATUS_SUCCESS
                                : ApplePaySession.STATUS_FAILURE);
                            window.location = result.resultUrl || 'index.php';
                        })
                        .catch((e) => {
                            session.completePayment(ApplePaySession.STATUS_FAILURE);
                            status('Payment error: ' + e);
                        });
                };

                session.begin();
            });
        })();
        </script>
    <?php endif; ?>
</section>
