<?php

/**
 * PayPal panel.
 *
 * No card, no token minted in the browser: the form posts method=paypal to
 * pay.php, which registers the transaction and (status Redirect, 2023) sends
 * the shopper to PayPal. Enrolment is only visible after redirect - an
 * unenrolled vendor fails at 1030.
 *
 * Expects from index.php scope: $order (array).
 *
 * @var array<string,string> $order
 */

$payPal = payPalReadiness();
?>
<section class="bg-white rounded-xl shadow p-6 space-y-4">
    <h2 class="text-lg font-semibold text-slate-800">PayPal</h2>

    <?php if (! $payPal['available']): ?>
        <?= methodPlaceholder($payPal) ?>
    <?php else: ?>
        <form method="post" action="pay.php" class="space-y-4">
            <?= orderHiddenInputs($order) ?>
            <?= browserHiddenInputs() ?>
            <input type="hidden" name="method" value="paypal">

            <p class="text-xs text-slate-500">
                A redirect flow: Opayo registers the transaction with
                <code>paymentMethod.paypal = {merchantSessionKey, callbackUrl}</code> and answers
                with a PayPal URL. After approving at the PayPal sandbox, Opayo returns you to
                <code>paypal-return.php</code>, which fetches the outcome.
                <?= h($payPal['detail']) ?>
            </p>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg">Pay with PayPal</button>
        </form>
    <?php endif; ?>
</section>
