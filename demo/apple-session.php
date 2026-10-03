<?php

/**
 * Apple Pay merchant validation (the onvalidatemerchant endpoint).
 *
 * When Safari raises ApplePaySession.onvalidatemerchant, the browser calls
 * this endpoint. We ask Opayo to open a merchant session (Opayo-managed
 * certificate path: no cert files on our side, just a domain registered in
 * MyOpayo), and return two things as JSON:
 *
 *   merchantSession        - passed straight to session.completeMerchantValidation()
 *   sessionValidationToken - kept by the browser and posted with the payment,
 *                            so pay.php can put it in the ApplePayPayment
 *
 * On failure we return HTTP 400 with the gateway's own message, which on an
 * unregistered domain (or 127.0.0.1) is 6118 / 6125.
 */

declare(strict_types=1);

require __DIR__ . '/shared.php';

use Academe\Opayo\Pi\Request\CreateApplePaySession;
use Academe\Opayo\Pi\Response\ApplePaySession;

header('Content-Type: application/json');

try {
    $response = sendAndRecord(
        new CreateApplePaySession(opayoEndpoint(), opayoAuth(), applePayDomain()),
        'Apple Pay merchant session (onvalidatemerchant)'
    );
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

if ($response instanceof ApplePaySession && $response->getMerchantSession()) {
    echo json_encode([
        'merchantSession' => $response->getMerchantSession(),
        'sessionValidationToken' => $response->getSessionValidationToken(),
    ]);
    exit;
}

// A failure is an ErrorCollection; quote the gateway's own code and message.
[$code, $description] = firstError($response);

http_response_code(400);
echo json_encode([
    'error' => $description ?: 'Opayo would not open an Apple Pay merchant session.',
    'code' => $code,
]);
