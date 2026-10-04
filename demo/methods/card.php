<?php

/**
 * Card, with Opayo's hosted card fields (sagepay.js drop-in).
 *
 * The shopper types card details into Opayo's iframe; sagepay.js tokenises
 * them in the browser and adds a card-identifier to the form. Your server
 * never sees the card number. The form posts to pay.php.
 *
 * Back end: nothing beyond the core. Extra endpoint: none (3D Secure returns to
 * notification.php, which every method shares).
 *
 * From checkout.php: $endpoint, $client, $auth, $order.
 */

use Academe\Opayo\Pi\Model\Endpoint;

/** @var Endpoint $endpoint */

try {
    // The drop-in tokenises in the browser, so it needs a session key now.
    $cardSessionKey = merchantSessionKey($client, $endpoint, $auth);
} catch (RuntimeException $e) {
    $cardSessionKey = null;
}
?>
<section class="bg-white rounded-xl shadow p-6 space-y-4">
    <h2 class="text-lg font-semibold text-slate-800">Card</h2>

    <?php if ($cardSessionKey === null): ?>
        <?= placeholder('Card payments are unavailable', 'Opayo would not issue a merchant session key; check your credentials.') ?>
    <?php else: ?>
        <form method="post" action="pay.php" data-pay class="space-y-4">
            <?= orderFields($order) ?>
            <input type="hidden" name="merchantSessionKey" value="<?= h($cardSessionKey) ?>">
            <div id="sp-container" class="border border-slate-200 rounded-lg"></div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg">Pay by card</button>
        </form>

        <script src="<?= h($endpoint->getJavascriptUrl()) ?>"></script>
        <script>
            // Renders the card fields into #sp-container and, on submit, adds
            // a hidden card-identifier to the surrounding form.
            sagepayCheckout({ merchantSessionKey: <?= json_encode($cardSessionKey) ?> }).form();
        </script>
    <?php endif; ?>
</section>
