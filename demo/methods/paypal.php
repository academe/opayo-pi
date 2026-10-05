<?php

/**
 * PayPal.
 *
 * No token is made in the browser: the form says "PayPal" and pay.php asks
 * Opayo to register the payment, then sends the shopper to PayPal. PayPal
 * returns them, via Opayo, to paypal-return.php.
 *
 * Back end: nothing beyond the core (PayPal is enabled on your Opayo account).
 * Extra endpoint: paypal-return.php.
 *
 * From checkout.php: $order.
 */
?>
<section class="bg-white rounded-xl shadow p-6 space-y-4">
    <h2 class="text-lg font-semibold text-slate-800">PayPal</h2>

    <form method="post" action="pay.php" data-pay>
        <?= orderFields($order) ?>
        <input type="hidden" name="method" value="paypal">
        <button type="submit" class="w-full bg-amber-400 hover:bg-amber-500 text-slate-900 font-semibold py-2 rounded-lg">Pay with PayPal</button>
    </form>
</section>
