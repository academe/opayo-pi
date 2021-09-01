<?php

namespace Academe\Opayo\Pi\Money;

/**
 * Value object for the amount, wrapping the moneyphp/money package.
 * Both v1.3 and v3.x (in alpha) should work.
 *
 * moneyphp/money is an optional package, so must be required manually if you want
 * to use it.
 */

use Money\Money;

class MoneyAmount implements AmountInterface
{
    protected $money;

    /**
     * MoneyAmount constructor.
     * @param Money $money
     */
    public function __construct(Money $money)
    {
        $this->money = $money;
    }

    /**
     * @return mixed
     */
    public function getAmount()
    {
        return $this->money->getAmount();
    }

    /**
     * @return mixed
     */
    public function getCurrencyCode()
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
