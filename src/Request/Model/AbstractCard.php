<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Model;

/**
 * Common functionality between all types of request card payment methods.
 */

abstract class AbstractCard implements PaymentMethodInterface
{
    /**
     * @var string|null Supplied when sending card identifier.
     */
    protected ?string $sessionKey = null;

    /**
     * @var string|null Tokenised card.
     */
    protected ?string $cardIdentifier = null;
}
