<?php

/**
 * Apple Pay merchant validation. Safari asks for this before showing the
 * sheet. Ask Opayo to open a merchant session for your registered domain and
 * hand it back to the browser, with the token pay.php will need.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Academe\Opayo\Pi\Factory\ResponseFactory;
use Academe\Opayo\Pi\Request\CreateApplePaySession;
use Academe\Opayo\Pi\Response\ApplePaySession;
use Academe\Opayo\Pi\Response\ErrorCollection;

header('Content-Type: application/json');

if (! in_array('applepay', $enabledMethods, true)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Apple Pay is not offered.', 'code' => null]));
}

$response = ResponseFactory::fromHttpResponse($client->sendRequest(
    new CreateApplePaySession($endpoint, $auth, $config['applePayDomain'])
));

if ($response instanceof ApplePaySession && $response->getMerchantSession()) {
    exit(json_encode([
        'merchantSession' => $response->getMerchantSession(),
        'sessionValidationToken' => $response->getSessionValidationToken(),
    ]));
}

// Opayo said no: pass on its own code and message (4006, 6118, 6125, ...).
$error = ['error' => 'Opayo would not open an Apple Pay merchant session.', 'code' => null];
if ($response instanceof ErrorCollection) {
    foreach ($response as $first) {
        $error = ['error' => $first->getDescription(), 'code' => $first->getCode()];
        break;
    }
}

http_response_code(400);
echo json_encode($error);
