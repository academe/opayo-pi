<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Checkout;

/**
 * What a payment response means for the next step, whatever the payment method.
 */
enum OutcomeKind: string
{
    /** Authorised or declined: show the result. */
    case Finished = 'finished';

    /** 3D Secure challenge: send the shopper's browser to the issuer's ACS. */
    case Challenge = 'challenge';

    /** PayPal: send the shopper to PayPal to approve. */
    case Redirect = 'redirect';

    /** Opayo rejected the request: show the errors. */
    case Rejected = 'rejected';
}
