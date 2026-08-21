<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Money;

use UnexpectedValueException;
use Alcohol\ISO4217;

/**
 * Defines a currency.
 * Only supports currencies that SagePay supports.
 */

class Currency implements CurrencyInterface
{
    private readonly ISO4217 $allCurrencies;

    /**
     * @param string $code The ISO 4217 alpha-3 currency code
     */
    public function __construct(
        private readonly string $code
    ) {
        $this->allCurrencies = new ISO4217();

        if (!$this->allCurrencies->getByAlpha3($code)) {
            throw new UnexpectedValueException(sprintf('Unsupported currency code "%s"', $code));
        }
    }

    /**
     * Return a new instance of a specified currency.
     * e.g. Currency::GBP()
     */
    public static function __callStatic(string $method, array $args): static
    {
        return new static($method);
    }

    /**
     * The ISO 4217 three-character currency code, e.g. "GBP".
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * The number of decimal digits in the minor unit, e.g. 2 for GBP
     * (100 pence to the pound) and 0 for JPY.
     */
    public function getMinorUnits(): int
    {
        return $this->allCurrencies->getByAlpha3($this->code)['exp'];
    }

    /**
     * @deprecated Use getMinorUnits()
     */
    public function getDigits(): int
    {
        return $this->getMinorUnits();
    }

    /**
     * The English name of the currency, e.g. "Pound Sterling".
     * Handy for display and logging, but not essential,
     * so it is not a part of the interface.
     */
    public function getName(): string
    {
        return $this->allCurrencies->getByAlpha3($this->code)['name'];
    }
}
