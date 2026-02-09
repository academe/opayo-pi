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

    public function getCode(): string
    {
        return $this->code;
    }

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
     * The symbols will be one or more UTF-8 characters.
     * getName and getSymbol are handy for display and logging, but not essential,
     * so they are not a part of the interface.
     */
    public function getName(): string
    {
        return $this->allCurrencies->getByAlpha3($this->code)['name'];
    }
}
