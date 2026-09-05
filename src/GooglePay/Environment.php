<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\GooglePay;

/**
 * Which Google Pay environment the browser's PaymentsClient runs in.
 *
 * This is Google's environment, not Opayo's, and the two are set separately:
 * a TEST sheet can be pointed at either Opayo endpoint, and so can a
 * PRODUCTION one.
 *
 * TEST never returns a chargeable token. Whichever card the shopper picks,
 * the sheet returns the fixed placeholder "examplePaymentMethodToken", which
 * Opayo rejects with "6203 Invalid Google Pay payload" - it has nothing to
 * decrypt. Only PRODUCTION mints a real, encrypted token, and it needs a
 * Google merchant ID and an allowlisted HTTPS origin.
 */
enum Environment: string
{
    case Test = 'TEST';
    case Production = 'PRODUCTION';

    /**
     * PRODUCTION requires the Google merchant ID in merchantInfo; TEST ignores it.
     */
    public function requiresGoogleMerchantId(): bool
    {
        return $this === self::Production;
    }
}
