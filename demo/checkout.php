<?php

/**
 * The checkout page: the order, then one front-end partial per payment method
 * this site offers. Choosing which methods to offer is the include list below,
 * driven by config ($enabledMethods), not code edits.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$order = [
    'amount' => '9.99',
    'description' => 'Demo purchase',
    'firstName' => 'Sam',
    'lastName' => 'Jones',
    'email' => 'sam.jones@example.com',
];

$labels = ['card' => 'Card', 'googlepay' => 'Google Pay', 'applepay' => 'Apple Pay', 'paypal' => 'PayPal'];

pageTop('Checkout');
?>
<section class="bg-white rounded-xl shadow p-6 space-y-4">
    <h2 class="text-lg font-semibold text-slate-800">Your order</h2>
    <div class="grid grid-cols-2 gap-4">
        <?php foreach ($order as $name => $value): ?>
            <label class="block text-sm">
                <span class="text-slate-600"><?= h(ucfirst($name)) ?></span>
                <input type="text" value="<?= h($value) ?>" data-order-src="<?= h($name) ?>" class="mt-1 w-full rounded border-slate-300">
            </label>
        <?php endforeach; ?>
    </div>
</section>

<?php foreach (DEMO_METHODS as $method => $switch): ?>
    <?php if (in_array($method, $enabledMethods, true)): ?>
        <?php include __DIR__ . '/methods/' . $method . '.php'; ?>
    <?php else: ?>
        <section class="bg-white rounded-xl shadow p-6">
            <?= placeholder($labels[$method] . ' is not offered', 'Switched off in .env (' . $switch . '=0).') ?>
        </section>
    <?php endif; ?>
<?php endforeach; ?>

<!-- In your app, serve resources/js/browser-data.js from your public assets. -->
<?= inlineScript(__DIR__ . '/../resources/js/browser-data.js') ?>
<script>
    // Every payment form carries the 3D Secure browser fields.
    document.querySelectorAll('form[data-pay]').forEach(OpayoBrowserData.fill);

    // Keep each form's hidden order fields in step with the order block.
    document.querySelectorAll('[data-order-src]').forEach(function (src) {
        src.addEventListener('input', function () {
            document.querySelectorAll('[data-order="' + src.dataset.orderSrc + '"]').forEach(function (hidden) {
                hidden.value = src.value;
            });
        });
    });
</script>
<?php
pageBottom();
