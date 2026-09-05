<?php

/**
 * Payment form.
 *
 * Two card-capture modes, switched with the tabs at the top:
 *
 *  - Server-side: the card details are posted to pay.php, which tokenises
 *    them itself. Fine for the sandbox with test cards; never do this with
 *    real cards.
 *  - Opayo JS drop-in: sagepay.js renders the card fields, tokenises the
 *    card in the browser using a merchant session key, and adds a hidden
 *    "card-identifier" input to the form before it submits. Card details
 *    never touch the PHP scripts.
 *  - PayPal: no card at all; paypal.php registers the transaction with the
 *    PayPal payment method and redirects the shopper to PayPal, who returns
 *    them to paypal-return.php.
 *  - Google Pay: the Google Pay sheet mints a token in the browser and
 *    googlepay.php sends it as paymentMethod.googlePay. Reaches Opayo but
 *    cannot be authorised from a TEST sheet - see googlepay.php.
 */

declare(strict_types=1);

require __DIR__ . '/shared.php';

use Academe\Opayo\Pi\GooglePay\Configuration as GooglePayConfiguration;
use Academe\Opayo\Pi\GooglePay\Environment as GooglePayEnvironment;
use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Money\Currency;

requireDottedHost();

$useJs = ! empty($_GET['js']);
$usePayPal = ! empty($_GET['paypal']);
$useGooglePay = ! empty($_GET['googlepay']);

// The drop-in tokenises in the browser, so it needs a session key now.
// In server-side mode pay.php creates its own.
$merchantSessionKey = $useJs ? createMerchantSessionKey() : null;

$testExpiry = date('my', strtotime('+2 years'));

// The Google Pay sheet's request objects, built by the library rather than
// hand-written in JavaScript. Opayo's own example is ~250 lines of this; the
// three lines that are actually about Opayo are the ones filled in here.
$googlePayClientConfig = $useGooglePay
    ? (new GooglePayConfiguration(
        gatewayMerchantId: googlePayMerchantId(),
        merchantName: 'Opayo Pi Demo',
        googleMerchantId: googlePayGoogleMerchantId() ?: null,
        environment: GooglePayEnvironment::Test,
    ))->clientConfiguration((new Amount(new Currency('GBP'), 0))->withMajorUnit('9.99'))
    : null;

pageTop($useGooglePay ? 'Google Pay' : ($usePayPal ? 'PayPal' : ($useJs ? 'Opayo JS drop-in' : 'Server-side capture')));

$tab = fn (bool $active) => 'flex items-center px-4 py-2 rounded-lg text-sm font-medium ' . ($active ? 'bg-blue-600 text-white' : 'bg-white text-slate-600');
?>

<?php $account = demoAccount(); $qsAccount = 'account=' . $account; ?>
<div class="space-y-3">
    <nav class="flex flex-wrap gap-2 items-stretch">
        <a href="index.php?<?= $qsAccount ?>" class="<?= $tab(! $useJs && ! $usePayPal && ! $useGooglePay) ?>">Server-side capture</a>
        <a href="index.php?js=1&amp;<?= $qsAccount ?>" class="<?= $tab($useJs) ?>">Opayo JS drop-in</a>
        <a href="index.php?paypal=1&amp;<?= $qsAccount ?>" class="<?= $tab($usePayPal) ?>">PayPal</a>
        <a href="index.php?googlepay=1&amp;<?= $qsAccount ?>" class="<?= $tab($useGooglePay) ?>">Google Pay</a>
    </nav>

    <!-- Changing account reloads the page so the session key (JS mode)
         is created against the right account. -->
    <form method="get" class="flex items-center text-sm">
        <?php if ($useJs): ?><input type="hidden" name="js" value="1"><?php endif; ?>
        <?php if ($usePayPal): ?><input type="hidden" name="paypal" value="1"><?php endif; ?>
        <?php if ($useGooglePay): ?><input type="hidden" name="googlepay" value="1"><?php endif; ?>
        <label class="text-slate-600">Account
            <select name="account" onchange="this.form.submit()" class="ml-1 rounded border-slate-300 text-sm">
                <option value="env" <?= $account === 'env' ? 'selected' : '' ?>>Your .env account</option>
                <option value="sandbox" <?= $account === 'sandbox' ? 'selected' : '' ?>>Public sandbox (3DS simulation)</option>
            </select>
        </label>
    </form>
</div>

<?php if ($account === 'env'): ?>
<div class="bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-lg p-3">
    Personal test accounts often have no 3D Secure simulation (3DS attempts are
    rejected with "3D-Authentication failed") and no wallets enabled (PayPal fails
    with "Vendor not enrolled with this wallet type"). For those flows, switch to the
    public sandbox profile above (credentials published by Elavon).
</div>
<?php endif; ?>

<?php if ($useGooglePay): ?>
<form id="googlepay-form" method="post" action="googlepay.php" class="bg-white rounded-xl shadow p-6 space-y-4">
    <input type="hidden" name="account" value="<?= h($account) ?>">
    <!-- Filled in by the Google Pay sheet, just before this form submits. -->
    <input type="hidden" name="googlePayToken" id="googlePayToken">

    <div class="grid grid-cols-2 gap-4">
        <label class="block text-sm">
            <span class="text-slate-600">Amount (GBP)</span>
            <input type="text" name="amount" id="gp-amount" value="9.99" class="mt-1 w-full rounded border-slate-300">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Description</span>
            <input type="text" name="description" value="Demo Google Pay purchase" class="mt-1 w-full rounded border-slate-300">
        </label>
    </div>

    <div class="grid grid-cols-3 gap-4">
        <label class="block text-sm">
            <span class="text-slate-600">First name</span>
            <input type="text" name="firstName" value="Sam" class="mt-1 w-full rounded border-slate-300">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Last name</span>
            <input type="text" name="lastName" value="Jones" class="mt-1 w-full rounded border-slate-300">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Email</span>
            <input type="email" name="email" value="sam.jones@example.com" class="mt-1 w-full rounded border-slate-300">
        </label>
    </div>

    <!-- The two knobs that decide whether the token is real. TEST always
         yields the placeholder token; PRODUCTION needs a Google merchant ID
         and an allowlisted origin, so it will not run on 127.0.0.1. -->
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

    <p class="text-xs text-slate-500">
        No card fields: the Google Pay sheet tokenises against
        <code>gateway: 'opayoelavon'</code> and this page posts
        <code>paymentData.paymentMethodData.tokenizationData.token</code> to
        <code>googlepay.php</code>, which sends it as
        <code>paymentMethod.googlePay = {merchantSessionKey, clientIpAddress, payload}</code>.
        The public sandbox vendor has the wallet enabled; a TEST sheet still stops at
        <code>6203 Invalid Google Pay payload</code>, because Google only mints a decryptable
        token in PRODUCTION.
    </p>

    <!-- 3D Secure device profile. Opayo's Google Pay guide recommends sending
         strongCustomerAuthentication so a PAN_ONLY card on a device without
         biometrics can still complete a challenge. Same fields as the card form. -->
    <input type="hidden" name="browserColorDepth" value="24">
    <input type="hidden" name="browserScreenHeight" value="1080">
    <input type="hidden" name="browserScreenWidth" value="1920">
    <input type="hidden" name="browserTz" value="0">
    <input type="hidden" name="browserLanguage" value="en-GB">

    <div id="googlepay-button" class="min-h-[44px]"></div>

    <p id="googlepay-status" class="text-xs text-red-600 hidden"></p>
</form>

<script src="https://pay.google.com/gp/p/js/pay.js"></script>
<script>
    // Built by Academe\Opayo\Pi\GooglePay\Configuration on the server. In a
    // real integration you would use this as-is; the demo lets you edit the
    // environment, gatewayMerchantId and amount, so it patches those in below.
    const OPAYO_GOOGLE_PAY = <?= json_encode($googlePayClientConfig) ?>;

    // Only read in PRODUCTION, which the server config is not built for.
    const GPAY_GOOGLE_MERCHANT_ID = <?= json_encode(googlePayGoogleMerchantId()) ?>;

    const gpStatus = (message) => {
        const el = document.getElementById('googlepay-status');
        el.textContent = message;
        el.classList.toggle('hidden', ! message);
    };

    /**
     * The server config with the demo's live form values applied.
     */
    function gpRequests() {
        const config = structuredClone(OPAYO_GOOGLE_PAY);
        const environment = document.getElementById('gp-environment').value;

        config.paymentDataRequest.allowedPaymentMethods[0].tokenizationSpecification
            .parameters.gatewayMerchantId = document.getElementById('gp-gateway-merchant-id').value.trim();

        config.paymentDataRequest.transactionInfo.totalPrice =
            document.getElementById('gp-amount').value.trim();

        if (environment === 'PRODUCTION') {
            config.paymentDataRequest.merchantInfo.merchantId = GPAY_GOOGLE_MERCHANT_ID;
        }

        config.environment = environment;

        return config;
    }

    // A PaymentsClient is bound to its environment, so switching the select
    // rebuilds both the client and the button it drew.
    function gpRenderButton() {
        const config = gpRequests();
        const client = new google.payments.api.PaymentsClient({environment: config.environment});
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
            const form = document.getElementById('googlepay-form');

            // Real device values for the 3D Secure profile.
            const depth = [1, 4, 8, 15, 16, 24, 32].includes(screen.colorDepth) ? screen.colorDepth : 24;
            form.browserColorDepth.value = depth;
            form.browserScreenHeight.value = screen.height;
            form.browserScreenWidth.value = screen.width;
            form.browserTz.value = new Date().getTimezoneOffset();
            form.browserLanguage.value = navigator.language || 'en-GB';

            // Post the whole token string; googlepay.php base64-encodes it.
            document.getElementById('googlePayToken').value = paymentData.paymentMethodData.tokenizationData.token;
            form.submit();
        }).catch((error) => {
            if (error.statusCode === 'CANCELED') {
                gpStatus('');
                return;
            }
            gpStatus('Google Pay sheet: ' + (error.statusMessage || error.statusCode || error));
        });
    }

    document.getElementById('gp-environment').addEventListener('change', gpRenderButton);
    gpRenderButton();
</script>

<?php pageBottom(); return; ?>
<?php endif; ?>

<?php if ($usePayPal): ?>
<form method="post" action="paypal.php" class="bg-white rounded-xl shadow p-6 space-y-4">
    <input type="hidden" name="account" value="<?= h($account) ?>">

    <div class="grid grid-cols-2 gap-4">
        <label class="block text-sm">
            <span class="text-slate-600">Amount (GBP)</span>
            <input type="text" name="amount" value="9.99" class="mt-1 w-full rounded border-slate-300">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Description</span>
            <input type="text" name="description" value="Demo PayPal purchase" class="mt-1 w-full rounded border-slate-300">
        </label>
    </div>

    <div class="grid grid-cols-3 gap-4">
        <label class="block text-sm">
            <span class="text-slate-600">First name</span>
            <input type="text" name="firstName" value="Sam" class="mt-1 w-full rounded border-slate-300">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Last name</span>
            <input type="text" name="lastName" value="Jones" class="mt-1 w-full rounded border-slate-300">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Email</span>
            <input type="email" name="email" value="sam.jones@example.com" class="mt-1 w-full rounded border-slate-300">
        </label>
    </div>

    <p class="text-xs text-slate-500">
        No card details: Opayo registers the transaction with
        <code>paymentMethod.paypal = {merchantSessionKey, callbackUrl}</code> and answers with a
        PayPal redirect URL. After the PayPal <em>sandbox</em> (buyer login needed), Opayo redirects
        you to <code><?= h(baseUrl()) ?>/paypal-return.php?transactionId=...</code>, which fetches
        the outcome. Requires a vendor with PayPal enabled - use the public sandbox.
    </p>

    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg">Pay with PayPal</button>
</form>

<div class="text-xs text-slate-500 space-y-1">
    <p>Google Pay has its own tab above. Apple Pay cannot be exercised here: it needs a real
       wallet token from Safari on an Apple device signed into a sandbox-tester Apple ID, plus a
       registered HTTPS domain. The library models it to the API reference
       (<code>ApplePayPayment</code>, <code>CreateApplePaySession</code>).</p>
</div>

<?php pageBottom(); return; ?>
<?php endif; ?>

<form id="payment-form" method="post" action="pay.php" class="bg-white rounded-xl shadow p-6 space-y-4">

    <input type="hidden" name="account" value="<?= h($account) ?>">

    <div class="grid grid-cols-2 gap-4">
        <label class="block text-sm">
            <span class="text-slate-600">Amount (GBP)</span>
            <input type="text" name="amount" value="9.99" class="mt-1 w-full rounded border-slate-300">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Description</span>
            <input type="text" name="description" value="Demo purchase" class="mt-1 w-full rounded border-slate-300">
        </label>
    </div>

    <div class="grid grid-cols-3 gap-4">
        <label class="block text-sm">
            <span class="text-slate-600">First name</span>
            <input type="text" name="firstName" value="Sam" class="mt-1 w-full rounded border-slate-300">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Last name</span>
            <input type="text" name="lastName" value="Jones" class="mt-1 w-full rounded border-slate-300">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Email</span>
            <input type="email" name="email" value="sam.jones@example.com" class="mt-1 w-full rounded border-slate-300">
        </label>
    </div>

    <?php if ($useJs): ?>
        <!-- sagepay.js renders its hosted card fields (an iframe) into this
             container, and adds a hidden card-identifier input on submit. -->
        <div id="sp-container" class="border border-slate-200 rounded-lg"></div>
        <div id="dropin-3ds-reminder" class="hidden bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg p-3">
            Type <code class="font-mono font-semibold">CHALLENGE</code> as the cardholder
            name above - the sandbox reads the 3DS outcome from it, and any other name
            simulates a failed authentication.
        </div>
        <input type="hidden" name="merchantSessionKey" value="<?= h($merchantSessionKey) ?>">
    <?php else: ?>
        <fieldset class="border border-slate-200 rounded-lg p-4 space-y-4">
            <legend class="text-sm font-medium text-slate-600 px-1">Card (Opayo test card prefilled)</legend>
            <label class="block text-sm">
                <span class="text-slate-600">Cardholder name</span>
                <input type="text" name="cardholderName" value="Sam Jones" class="mt-1 w-full rounded border-slate-300">
            </label>
            <div class="grid grid-cols-3 gap-4">
                <label class="block text-sm col-span-1">
                    <span class="text-slate-600">Card number</span>
                    <input type="text" name="cardNumber" value="4929000000006" class="mt-1 w-full rounded border-slate-300">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Expiry (MMYY)</span>
                    <input type="text" name="cardExpiry" value="<?= h($testExpiry) ?>" class="mt-1 w-full rounded border-slate-300">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">CVV</span>
                    <input type="text" name="cardCvv" value="123" class="mt-1 w-full rounded border-slate-300">
                </label>
            </div>
        </fieldset>
    <?php endif; ?>

    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" name="use3ds" value="1" id="use3ds" class="rounded border-slate-300">
        Use 3D Secure v2 (redirects to the sandbox challenge, returns to notification.php)
    </label>

    <p class="text-xs text-slate-500">
        With 3D Secure on, the <em>cardholder name</em> is a sandbox magic value that picks the
        outcome: <code>CHALLENGE</code> (challenge flow), <code>SUCCESSFUL</code> (frictionless
        pass), <code>NOTAUTH</code> (frictionless fail). Ticking the box sets it to
        <code>CHALLENGE</code> for you<?= $useJs ? ' - type it as the name in the card form' : '' ?>.
    </p>

    <!-- Real browser details for the strongCustomerAuthentication object,
         filled in by the script below. -->
    <input type="hidden" name="browserColorDepth" value="24">
    <input type="hidden" name="browserScreenHeight" value="1080">
    <input type="hidden" name="browserScreenWidth" value="1920">
    <input type="hidden" name="browserTz" value="0">
    <input type="hidden" name="browserLanguage" value="en-GB">

    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg">Pay</button>
</form>

<div class="text-xs text-slate-500 space-y-1">
    <p>Test cards: Visa 4929000000006, MasterCard 5404000000000001 (CVV 123, any future expiry).</p>
    <p>With 3D Secure on, the sandbox challenge page appears; the ACS returns you to
       <code><?= h(baseUrl()) ?>/notification.php</code> through your browser, which is why
       localhost works without a public URL.</p>
</div>

<script>
    // Collect real browser details for Strong Customer Authentication.
    const form = document.getElementById('payment-form');
    const allowedDepths = [1, 4, 8, 15, 16, 24, 32, 48];
    const depth = allowedDepths.includes(screen.colorDepth) ? screen.colorDepth : 24;
    form.browserColorDepth.value = depth;
    form.browserScreenHeight.value = screen.height;
    form.browserScreenWidth.value = screen.width;
    form.browserTz.value = new Date().getTimezoneOffset();
    form.browserLanguage.value = navigator.language || 'en-GB';

    // With 3DS on, the sandbox reads the outcome from the cardholder name.
    // In server-side mode we can set it; in drop-in mode the name field is
    // inside Opayo's iframe, so show a reminder instead.
    document.getElementById('use3ds').addEventListener('change', function () {
        if (form.cardholderName) {
            form.cardholderName.value = this.checked ? 'CHALLENGE' : 'Sam Jones';
        }
        const reminder = document.getElementById('dropin-3ds-reminder');
        if (reminder) {
            reminder.classList.toggle('hidden', !this.checked);
        }
    });
</script>

<?php if ($useJs): ?>
<script src="<?= h(opayoEndpoint()->getJavascriptUrl()) ?>"></script>
<script>
    // The drop-in renders card fields inside #payment-form, and on submit
    // tokenises them and adds a hidden "card-identifier" input.
    sagepayCheckout({
        merchantSessionKey: '<?= h($merchantSessionKey) ?>'
    }).form();
</script>
<?php endif; ?>

<?php pageBottom(); ?>
