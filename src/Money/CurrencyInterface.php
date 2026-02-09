<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Money;

/**
 * Currency interface, for defining a currency instance.
 */

interface CurrencyInterface
{
    /**
     * @return string The ISO 4217 three-character currency code
     */
    public function getCode(): string;

    /**
     * @return int The number of digits in the decimal subunit
     */
    public function getMinorUnits(): int;
}
