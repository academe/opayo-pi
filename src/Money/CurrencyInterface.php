<?php

namespace Academe\Opayo\Pi\Money;

/**
 * Currency interface, for defining a currency instance.
 */

interface CurrencyInterface
{
    /**
     * @return string The ISO 4217 three-character currency code
     */
    public function getCode();

    /**
     * @return mixed The number of digits in the decimal subunit
     */
    public function getMinorUnits();
}
