<?php

/**
 * The one result page. Whichever method was used, and whichever route the
 * result came back by (pay.php, notification.php, paypal-return.php), it ends
 * here with a PaymentOutcome summary in the session.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Academe\Opayo\Pi\Response\Model\AdditionalDeclineDetail;

$outcome = $_SESSION['outcome'] ?? null;
unset($_SESSION['outcome'], $_SESSION['transactionId'], $_SESSION['paymentMethod']);

if ($outcome === null) {
    header('Location: checkout.php');
    exit;
}

[$heading, $colour] = match (true) {
    $outcome['successful'] => ['Payment successful', 'text-emerald-600'],
    $outcome['kind'] === 'redirect' => ['Waiting for PayPal approval', 'text-amber-600'],
    $outcome['kind'] === 'rejected' => ['Opayo rejected the payment request', 'text-red-600'],
    default => ['Payment not authorised', 'text-red-600'],
};

pageTop('Result');
?>
<section class="bg-white rounded-xl shadow p-6 space-y-3">
    <h2 class="text-lg font-semibold <?= $colour ?>"><?= h($heading) ?></h2>

    <?php if ($outcome['kind'] === 'rejected'): ?>
        <ul class="text-sm text-slate-700 list-disc pl-5 space-y-1">
            <?php foreach ($outcome['errors'] as $error): ?>
                <li><?= h($error['description'] ?? 'Unknown error') ?>
                    <span class="text-slate-400">(<?= h((string) ($error['code'] ?? $error['property'] ?? '-')) ?>)</span></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <dl class="text-sm grid grid-cols-[10rem_1fr] gap-y-1">
            <dt class="text-slate-500">Status</dt><dd class="font-mono"><?= h($outcome['status']) ?></dd>
            <dt class="text-slate-500">Detail</dt><dd class="font-mono"><?= h($outcome['statusDetail']) ?></dd>
            <dt class="text-slate-500">Transaction ID</dt><dd class="font-mono"><?= h($outcome['transactionId']) ?></dd>
            <?php if (($outcome['settlementReferenceText'] ?? null) !== null): ?>
                <dt class="text-slate-500">Settlement reference</dt><dd class="font-mono"><?= h($outcome['settlementReferenceText']) ?></dd>
            <?php endif; ?>
            <?php if (($outcome['declineDetail'] ?? null) !== null): ?>
                <dt class="text-slate-500">Decline code</dt>
                <dd class="font-mono"><?= h($outcome['declineDetail']['code']) ?>
                    <?= h($outcome['declineDetail']['description']) ?></dd>
                <dt class="text-slate-500">Decline category</dt>
                <dd class="font-mono"><?= h($outcome['declineDetail']['category']) ?>
                    <span class="font-sans text-slate-500"><?= h(match ($outcome['declineDetail']['category']) {
                        AdditionalDeclineDetail::CATEGORY_DO_NOT_RETRY => 'do not try again within 30 days',
                        AdditionalDeclineDetail::CATEGORY_RETRY_LATER => 'the issuer cannot approve now; try again later',
                        AdditionalDeclineDetail::CATEGORY_CORRECT_DATA => 'correct the card details before trying again',
                        AdditionalDeclineDetail::CATEGORY_RETRY => 'trying again is permitted',
                        default => '',
                    }) ?></span></dd>
            <?php endif; ?>
        </dl>
    <?php endif; ?>

    <a href="checkout.php" class="inline-block text-sm text-blue-600 hover:underline">&larr; Back to checkout</a>
</section>
<?php
pageBottom();
