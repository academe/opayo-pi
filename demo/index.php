<?php

/**
 * Checkout: one page offering every payment type at once.
 *
 * The order (amount, description, customer) is entered once at the top. Each
 * payment method is a self-contained panel in demo/methods/, which decides for
 * itself whether to show its live control or a placeholder stating why it
 * cannot run. Every panel posts the same order to pay.php with its own
 * credential; pay.php is the single endpoint that turns any credential into a
 * CreatePayment.
 *
 * There are no tabs and no per-method pages: the point is to see all methods,
 * and their integration code, side by side.
 */

declare(strict_types=1);

require __DIR__ . '/shared.php';

requireDottedHost();

$account = demoAccount();
$order = defaultOrder();

// The card drop-in tokenises in the browser, so it needs a session key at
// render. Wallets and PayPal get their own inside pay.php.
$merchantSessionKey = demoMethodEnabled('card') ? createMerchantSessionKey() : null;
$testExpiry = date('my', strtotime('+2 years'));

pageTop('Checkout');
?>

<!-- Account selector: which Opayo profile the whole page runs against. -->
<form method="get" class="flex items-center text-sm">
    <label class="text-slate-600">Account
        <select name="account" onchange="this.form.submit()" class="ml-1 rounded border-slate-300 text-sm">
            <option value="env" <?= $account === 'env' ? 'selected' : '' ?>>Your .env account</option>
            <option value="sandbox" <?= $account === 'sandbox' ? 'selected' : '' ?>>Public sandbox (3DS simulation)</option>
        </select>
    </label>
</form>

<?php if ($account === 'env'): ?>
<div class="bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-lg p-3">
    Personal test accounts often have no 3D Secure simulation (3DS attempts are rejected with
    "3D-Authentication failed") and may not have every wallet enabled. For those flows, switch to
    the public sandbox profile above (credentials published by Elavon).
</div>
<?php endif; ?>

<!-- The order, entered once. Edits mirror into every method form below. -->
<section class="bg-white rounded-xl shadow p-6 space-y-4">
    <h2 class="text-lg font-semibold text-slate-800">Your order</h2>
    <div class="grid grid-cols-2 gap-4">
        <label class="block text-sm">
            <span class="text-slate-600">Amount (GBP)</span>
            <input type="text" value="<?= h($order['amount']) ?>" data-order-src="amount" class="mt-1 w-full rounded border-slate-300">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Description</span>
            <input type="text" value="<?= h($order['description']) ?>" data-order-src="description" class="mt-1 w-full rounded border-slate-300">
        </label>
    </div>
    <div class="grid grid-cols-3 gap-4">
        <label class="block text-sm">
            <span class="text-slate-600">First name</span>
            <input type="text" value="<?= h($order['firstName']) ?>" data-order-src="firstName" class="mt-1 w-full rounded border-slate-300">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Last name</span>
            <input type="text" value="<?= h($order['lastName']) ?>" data-order-src="lastName" class="mt-1 w-full rounded border-slate-300">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Email</span>
            <input type="email" value="<?= h($order['email']) ?>" data-order-src="email" class="mt-1 w-full rounded border-slate-300">
        </label>
    </div>
    <p class="text-xs text-slate-500">One order, four ways to pay. Each panel below shows its own
        integration; an unavailable method shows why rather than disappearing.</p>
</section>

<?php
// Each method is self-contained: it reads its own readiness and renders a live
// control or a placeholder. All four are always included; a disabled or
// unavailable one shows a note.
include __DIR__ . '/methods/card.php';
include __DIR__ . '/methods/googlepay.php';
include __DIR__ . '/methods/applepay.php';
include __DIR__ . '/methods/paypal.php';
?>

<script>
    // Mirror the single order block into every method form's hidden inputs, so
    // whichever button the shopper uses posts the current order.
    document.querySelectorAll('[data-order-src]').forEach(function (src) {
        src.addEventListener('input', function () {
            const name = src.getAttribute('data-order-src');
            document.querySelectorAll('[data-order="' + name + '"]').forEach(function (hidden) {
                hidden.value = src.value;
            });
        });
    });
</script>

<?php pageBottom(); ?>
