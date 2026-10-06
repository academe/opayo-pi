<?php

/**
 * What each error code the demo is known to reach means, and what to do.
 */

declare(strict_types=1);

function debugExplain(string|int|null $code): string
{
    return match ((string) $code) {
        '6125' => 'The served host carries a port (e.g. 127.0.0.1:8000), which is not a valid Apple Pay domain. '
            . 'Serve the demo from a real HTTPS domain: see demo/README.md, "Running it publicly".',
        '6118' => 'The domain is not registered in MyOpayo > Settings > Pay Methods > Apple Pay. Register it, '
            . 'or correct OPAYO_APPLE_PAY_DOMAIN in .env.',
        '4006' => 'The domain is fine; this account cannot open an Opayo-managed Apple Pay session. Elavon have '
            . 'confirmed the sandbox only supports the merchant-managed certificate mode, so 4006 is the expected '
            . 'and final result on a test account. It can only be verified on a live account.',
        '6203' => 'Google Pay is enabled on this account: Opayo got as far as the payload and rejected it. The '
            . 'sandbox does not accept tokens from Google\'s TEST environment, so this is as far as it goes; '
            . 'completing a Google Pay payment needs PRODUCTION on a live account.',
        '6111' => 'The Apple Pay paymentData is not valid base64. Build the payment method with '
            . 'ApplePayPayment::fromAppleToken(), which does the encoding.',
        '6146' => 'The Apple Pay paymentData decodes, but not to JSON. It was probably encoded twice. Pass '
            . 'Apple\'s token to ApplePayPayment::fromAppleToken() as it arrived.',
        '6151' => 'The Apple Pay paymentData has no top-level "paymentData" key. Send {"paymentData": {...}}, '
            . 'not the bare contents and not a "token" wrapper. ApplePayPayment::fromAppleToken() does this.',
        '6138' => 'Opayo read the Apple Pay payload and could not decrypt it. The request is well formed; the '
            . 'token itself is not one Opayo can open (a test or made-up token, or the wrong certificate).',
        '6401' => 'This wallet is not enabled for the vendor. Ask Opayo to enable it, or switch to the public '
            . 'sandbox account.',
        '1030' => 'PayPal is not enabled for the vendor. Ask Opayo to enable it, or switch to the public sandbox '
            . 'account.',
        default => 'See docs/CREDENTIALS-AND-SETUP.md for the setup steps and the error-code table.',
    };
}
