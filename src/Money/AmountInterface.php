<?php

namespace Academe\Opayo\Pi\Money;

/**
 * Amount interface, for carrying an amount and its currency.
 */

interface AmountInterface
{
    /**
     * Return the amount, always in integer minor units.
     * For example, return 123 for amount 1.23 USD.
     *
     * @return int The amount, always in integer minor units
     */
    public function getAmount();

    /**
     * Return the currency ISO code.
     * For example "GBP" for UK Pound (£).
     *
     * @return string The ISO 4217 three-character currency code
     */
    public function getCurrencyCode();
}
