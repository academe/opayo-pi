<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Money;

use Money\Money;

/**
 * Value object for the amount, wrapping the moneyphp/money package.
 * Both v1.3 and v3.x (in alpha) should work.
 *
 * moneyphp/money is an optional package, so must be required manually if you want
 * to use it.
 */

class MoneyAmount implements AmountInterface
{
    public function __construct(protected readonly Money $money)
    {
    }

    public function getAmount(): string
    {
        return $this->money->getAmount();
    }

    public function getCurrencyCode(): string
    {
        $currency = $this->money->getCurrency();

        // To support Money ~1.x and ~3.x
        if (method_exists($currency, 'getCode')) {
            return $currency->getCode();
        } else {
            return $currency->getName();
        }
    }
}
