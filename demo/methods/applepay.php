<?php

/**
 * Apple Pay (web, Opayo-managed certificate).
 *
 * Safari shows the Apple Pay sheet. Before it opens, Apple asks your server to
 * validate the merchant: apple-session.php asks Opayo to open a merchant
 * session for your registered domain. After the shopper authorises, the token
 * is posted to pay.php by fetch, because the sheet must be completed with the
 * real outcome before the page moves on.
 *
 * Back end: $config['applePayDomain'], registered in MyOpayo.
 * Extra endpoint: apple-session.php.
 *
 * Opayo's sandbox does not support this certificate mode (it answers 4006),
 * so this flow can only be completed on a live account.
 *
 * From checkout.php: $config, $order.
 */
?>
<section class="bg-white rounded-xl shadow p-6 space-y-4">
    <h2 class="text-lg font-semibold text-slate-800">Apple Pay</h2>

    <form id="applepay-form" method="post" action="pay.php" data-pay>
        <?= orderFields($order) ?>
        <input type="hidden" name="applePayToken">
        <input type="hidden" name="appleSessionValidationToken">
        <input type="hidden" name="resultFormat" value="json">
        <div id="applepay-button" class="hidden" style="-apple-pay-button-style: black; -webkit-appearance: -apple-pay-button; height: 44px; width: 100%;"></div>
        <p id="applepay-status" class="text-xs text-slate-500"></p>
    </form>

    <script>
    (function () {
        const VERSION = 6;
        const form = document.getElementById('applepay-form');
        const button = document.getElementById('applepay-button');
        const status = (message) => { document.getElementById('applepay-status').textContent = message; };

        // Only Safari on an Apple device with a card in Wallet can pay.
        if (! window.ApplePaySession || ! ApplePaySession.supportsVersion(VERSION) || ! ApplePaySession.canMakePayments()) {
            status('Apple Pay needs Safari on an Apple device with a card in Wallet.');
            return;
        }
        button.classList.remove('hidden');

        button.addEventListener('click', function () {
            const session = new ApplePaySession(VERSION, {
                countryCode: 'GB',
                currencyCode: 'GBP',
                merchantCapabilities: ['supports3DS'],
                supportedNetworks: ['visa', 'masterCard', 'amex'],
                total: { label: <?= json_encode($config['merchantName']) ?>, amount: form.querySelector('[data-order="amount"]').value.trim() },
            });

            session.onvalidatemerchant = function () {
                fetch('apple-session.php', { method: 'POST' })
                    .then((r) => r.json())
                    .then((data) => {
                        if (data.error) {
                            status('Merchant validation failed: ' + data.error + (data.code ? ' (' + data.code + ')' : ''));
                            session.abort();
                            return;
                        }
                        form.elements.appleSessionValidationToken.value = data.sessionValidationToken || '';
                        session.completeMerchantValidation(data.merchantSession);
                    })
                    .catch((error) => { status('Merchant validation error: ' + error); session.abort(); });
            };

            session.onpaymentauthorized = function (event) {
                form.elements.applePayToken.value = JSON.stringify(event.payment.token);

                fetch('pay.php', { method: 'POST', body: new FormData(form) })
                    .then((r) => r.json())
                    .then((result) => {
                        session.completePayment(result.approved ? ApplePaySession.STATUS_SUCCESS : ApplePaySession.STATUS_FAILURE);
                        window.location = result.completeUrl;
                    })
                    .catch((error) => {
                        session.completePayment(ApplePaySession.STATUS_FAILURE);
                        status('Payment error: ' + error);
                    });
            };

            session.begin();
        });
    })();
    </script>
</section>
