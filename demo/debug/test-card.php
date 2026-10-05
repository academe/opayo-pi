<?php

/**
 * Test card: tokenise a sandbox card on the server, choosing the cardholder
 * name that drives the sandbox's 3D Secure simulation, then pay through the
 * normal pay.php. Demo only, and never against live: real card numbers must
 * not touch your server.
 */

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

if (! function_exists('debugRecord')) {
    http_response_code(404);
    exit('The debug layer is off (DEMO_DEBUG=0).');
}

use Academe\Opayo\Pi\Factory\ResponseFactory;
use Academe\Opayo\Pi\Request\CreateCardIdentifier;
use Academe\Opayo\Pi\Response\CardIdentifier;

if (! $endpoint->isTesting()) {
    http_response_code(403);
    exit('The test card page only runs against the Opayo test endpoint.');
}

$names = ['CHALLENGE', 'SUCCESSFUL', 'NOTAUTH', 'PROOFATTEMPT', 'NOTENROLLED', 'REJECT', 'TECHDIFFICULTIES', 'ERROR'];
$expiry = date('my', strtotime('+2 years'));
$order = ['amount' => '9.99', 'description' => 'Test card purchase', 'firstName' => 'Sam', 'lastName' => 'Jones', 'email' => 'sam.jones@example.com'];

pageTop('Test card');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionKey = merchantSessionKey($client, $endpoint, $auth);
    $response = ResponseFactory::fromHttpResponse($client->sendRequest(new CreateCardIdentifier(
        $endpoint,
        $auth,
        $sessionKey,
        $_POST['cardholderName'] ?? 'CHALLENGE',
        $_POST['cardNumber'] ?? '4929000000006',
        $_POST['cardExpiry'] ?? $expiry,
        ($_POST['cardCvv'] ?? '') ?: null
    )));

    if (! $response instanceof CardIdentifier) {
        echo placeholder('Opayo would not tokenise the card', 'See the wire panel for the error.');
    } else {
        ?>
        <section class="bg-white rounded-xl shadow p-6 space-y-4">
            <p class="text-sm text-slate-700">Card tokenised. This posts the card-identifier to the normal <code>pay.php</code>.</p>
            <form method="post" action="../pay.php" data-pay>
                <?= orderFields($order) ?>
                <input type="hidden" name="merchantSessionKey" value="<?= h($sessionKey) ?>">
                <input type="hidden" name="card-identifier" value="<?= h($response->getCardIdentifier()) ?>">
                <button class="w-full bg-blue-600 text-white font-semibold py-2 rounded-lg">Pay with this card</button>
            </form>
        </section>
        <?= inlineScript(__DIR__ . '/../../resources/js/browser-data.js') ?>
        <script>document.querySelectorAll('form[data-pay]').forEach(OpayoBrowserData.fill);</script>
        <?php
    }
} else {
    ?>
    <section class="bg-white rounded-xl shadow p-6 space-y-4">
        <p class="text-sm text-slate-700">The sandbox picks the 3D Secure outcome from the cardholder name. To be
            challenged, set <code>OPAYO_APPLY_3D_SECURE=Force</code> in <code>.env</code> and use the public sandbox account.</p>
        <form method="post" class="space-y-3 text-sm">
            <label class="block">Cardholder name
                <select name="cardholderName" class="mt-1 w-full rounded border-slate-300">
                    <?php foreach ($names as $name): ?><option><?= h($name) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="block">Card number <input name="cardNumber" value="4929000000006" class="mt-1 w-full rounded border-slate-300"></label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block">Expiry (MMYY) <input name="cardExpiry" value="<?= h($expiry) ?>" class="mt-1 w-full rounded border-slate-300"></label>
                <label class="block">CVV <input name="cardCvv" value="123" class="mt-1 w-full rounded border-slate-300"></label>
            </div>
            <button class="w-full bg-blue-600 text-white font-semibold py-2 rounded-lg">Tokenise the test card</button>
        </form>
    </section>
    <?php
}

pageBottom();
