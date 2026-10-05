<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

use Academe\Opayo\Pi\Money\AmountInterface;
use Academe\Opayo\Pi\Money\MoneyAmount;
use Money\Money;

/**
 * Lets a request constructor accept either the package's own AmountInterface
 * (Amount, MoneyAmount, or a custom implementation) or a moneyphp/money
 * Money instance directly, and silently converts the latter.
 *
 * moneyphp/money is optional: naming Money in a union type is only an
 * instanceof check at call time, so nothing here needs the package to be
 * installed unless a Money instance is actually passed.
 */

trait AmountNormaliserTrait
{
    /**
     * @param AmountInterface|Money $amount
     * @return AmountInterface
     */
    protected static function normaliseAmount(AmountInterface|Money $amount): AmountInterface
    {
        if ($amount instanceof AmountInterface) {
            return $amount;
        }

        return new MoneyAmount($amount);
    }
}
