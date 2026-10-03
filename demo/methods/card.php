<?php

/**
 * Card panel.
 *
 * The hosted-fields drop-in (sagepay.js) is the default and the thing to copy:
 * card details are tokenised in the browser and never touch your PHP. A toggle
 * reveals server-side capture (posting the raw PAN), kept only because it is
 * the one way to drive the sandbox's magic cardholder names without typing into
 * Opayo's iframe.
 *
 * Expects, from index.php's scope: $order (array), $merchantSessionKey (string),
 * $testExpiry (string).
 *
 * @var array<string,string> $order
 * @var string $merchantSessionKey
 * @var string $testExpiry
 */

$card = cardReadiness();
?>
<section class="bg-white rounded-xl shadow p-6 space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-800">Card</h2>
        <span class="text-xs font-medium text-emerald-600">Recommended: hosted fields</span>
    </div>

    <?php if (! $card['available']): ?>
        <?= methodPlaceholder($card) ?>
    <?php else: ?>
        <div class="flex gap-2 text-sm">
            <button type="button" id="card-mode-dropin"
                class="px-3 py-1 rounded-lg bg-blue-600 text-white font-medium">Hosted fields (recommended)</button>
            <button type="button" id="card-mode-server"
                class="px-3 py-1 rounded-lg bg-white text-slate-600 border border-slate-300">Server-side capture</button>
        </div>

        <!-- Hosted-fields drop-in: sagepay.js renders card fields into this form
             and adds a hidden card-identifier on submit. -->
        <form id="card-dropin-form" method="post" action="pay.php" class="space-y-4">
            <?= orderHiddenInputs($order) ?>
            <?= browserHiddenInputs() ?>
            <input type="hidden" name="merchantSessionKey" value="<?= h($merchantSessionKey) ?>">
            <div id="sp-container" class="border border-slate-200 rounded-lg"></div>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="use3ds" value="1" class="rounded border-slate-300" data-use3ds>
                Use 3D Secure v2
            </label>
            <div data-3ds-reminder class="hidden bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg p-3">
                Type <code class="font-mono font-semibold">CHALLENGE</code> as the cardholder name -
                the sandbox reads the 3DS outcome from it, and any other name simulates a failure.
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg">Pay by card</button>
        </form>

        <!-- Server-side capture: the raw PAN is posted and tokenised by pay.php.
             Fine for the sandbox; never do this with real cards. -->
        <form id="card-server-form" method="post" action="pay.php" class="space-y-4 hidden">
            <?= orderHiddenInputs($order) ?>
            <?= browserHiddenInputs() ?>
            <fieldset class="border border-slate-200 rounded-lg p-4 space-y-4">
                <legend class="text-sm font-medium text-slate-600 px-1">Card (Opayo test card prefilled)</legend>
                <label class="block text-sm">
                    <span class="text-slate-600">Cardholder name</span>
                    <input type="text" name="cardholderName" value="Sam Jones" class="mt-1 w-full rounded border-slate-300" data-cardholder>
                </label>
                <div class="grid grid-cols-3 gap-4">
                    <label class="block text-sm">
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
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="use3ds" value="1" class="rounded border-slate-300" data-use3ds data-sets-cardholder>
                Use 3D Secure v2 (sets cardholder name to <code class="font-mono">CHALLENGE</code>)
            </label>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg">Pay by card (server-side)</button>
        </form>

        <p class="text-xs text-slate-500">
            Test cards: Visa <code>4929000000006</code>, Mastercard <code>5404000000000001</code>
            (CVV 123, any future expiry). With 3D Secure on, the cardholder name is the sandbox magic
            value: <code>CHALLENGE</code>, <code>SUCCESSFUL</code>, or <code>NOTAUTH</code>.
        </p>

        <script src="<?= h(opayoEndpoint()->getJavascriptUrl()) ?>"></script>
        <script>
        (function () {
            // Toggle between the two card capture modes.
            const dropinBtn = document.getElementById('card-mode-dropin');
            const serverBtn = document.getElementById('card-mode-server');
            const dropinForm = document.getElementById('card-dropin-form');
            const serverForm = document.getElementById('card-server-form');

            function show(mode) {
                const dropin = mode === 'dropin';
                dropinForm.classList.toggle('hidden', ! dropin);
                serverForm.classList.toggle('hidden', dropin);
                dropinBtn.className = 'px-3 py-1 rounded-lg font-medium ' + (dropin ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-300');
                serverBtn.className = 'px-3 py-1 rounded-lg font-medium ' + (dropin ? 'bg-white text-slate-600 border border-slate-300' : 'bg-blue-600 text-white');
            }
            dropinBtn.addEventListener('click', () => show('dropin'));
            serverBtn.addEventListener('click', () => show('server'));

            // 3DS: server-side can set the magic cardholder name; the drop-in
            // cannot reach inside Opayo's iframe, so it shows a reminder instead.
            serverForm.querySelector('[data-use3ds]').addEventListener('change', function () {
                serverForm.querySelector('[data-cardholder]').value = this.checked ? 'CHALLENGE' : 'Sam Jones';
            });
            dropinForm.querySelector('[data-use3ds]').addEventListener('change', function () {
                dropinForm.querySelector('[data-3ds-reminder]').classList.toggle('hidden', ! this.checked);
            });

            // The drop-in renders its fields into #sp-container and adds a hidden
            // card-identifier to #card-dropin-form on submit.
            sagepayCheckout({ merchantSessionKey: '<?= h($merchantSessionKey) ?>' }).form();
        })();
        </script>
    <?php endif; ?>
</section>
