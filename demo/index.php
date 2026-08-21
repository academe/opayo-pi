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
 */

declare(strict_types=1);

require __DIR__ . '/shared.php';

requireDottedHost();

$useJs = ! empty($_GET['js']);

// The drop-in tokenises in the browser, so it needs a session key now.
// In server-side mode pay.php creates its own.
$merchantSessionKey = $useJs ? createMerchantSessionKey() : null;

$testExpiry = date('my', strtotime('+2 years'));

pageTop($useJs ? 'Opayo JS drop-in' : 'Server-side capture');
?>

<?php $account = demoAccount(); $qsAccount = 'account=' . $account; ?>
<nav class="flex gap-2 items-center">
    <a href="index.php?<?= $qsAccount ?>" class="px-4 py-2 rounded-lg text-sm font-medium <?= $useJs ? 'bg-white text-slate-600' : 'bg-blue-600 text-white' ?>">Server-side capture</a>
    <a href="index.php?js=1&amp;<?= $qsAccount ?>" class="px-4 py-2 rounded-lg text-sm font-medium <?= $useJs ? 'bg-blue-600 text-white' : 'bg-white text-slate-600' ?>">Opayo JS drop-in</a>

    <!-- Changing account reloads the page so the session key (JS mode)
         is created against the right account. -->
    <form method="get" class="ml-auto text-sm">
        <?php if ($useJs): ?><input type="hidden" name="js" value="1"><?php endif; ?>
        <label class="text-slate-600">Account
            <select name="account" onchange="this.form.submit()" class="rounded border-slate-300 text-sm">
                <option value="env" <?= $account === 'env' ? 'selected' : '' ?>>Your .env account</option>
                <option value="sandbox" <?= $account === 'sandbox' ? 'selected' : '' ?>>Public sandbox (3DS simulation)</option>
            </select>
        </label>
    </form>
</nav>

<?php if ($account === 'env'): ?>
<div class="bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-lg p-3">
    Personal test accounts often have no 3D Secure simulation, so 3DS attempts are
    rejected with "3D-Authentication failed". For the 3DS flows, switch to the
    public sandbox profile above (credentials published by Elavon).
</div>
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
<script src="https://sandbox.opayo.eu.elavon.com/api/v1/js/sagepay.js"></script>
<script>
    // The drop-in renders card fields inside #payment-form, and on submit
    // tokenises them and adds a hidden "card-identifier" input.
    sagepayCheckout({
        merchantSessionKey: '<?= h($merchantSessionKey) ?>'
    }).form();
</script>
<?php endif; ?>

<?php pageBottom(); ?>
