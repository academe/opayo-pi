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
 * TEST never returns a chargeable token. The sheet returns a genuine,
 * encrypted token signed with Google's test key, which Opayo's sandbox
 * rejects with "6203 Invalid Google Pay payload": it does not accept TEST
 * tokens. Only PRODUCTION tokens can be charged, and they need a Google
 * merchant ID, an allowlisted HTTPS origin and a live Opayo account.
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
