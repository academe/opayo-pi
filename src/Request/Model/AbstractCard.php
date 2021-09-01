<?php

namespace Academe\Opayo\Pi\Request\Model;

/**
 * Common functionality between all types of request card payment methods.
 */

abstract class AbstractCard implements PaymentMethodInterface
{
    /**
     * @var Supplied when sending card identifier.
     */
    protected $sessionKey;

    /**
     * @var Tokenised card.
     */
    protected $cardIdentifier;
}
