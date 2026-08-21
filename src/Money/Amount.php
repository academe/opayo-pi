<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Money;

use Money\Money;
use UnexpectedValueException;
use Exception;

/**
 * Value object for the amount, in the appropriate currency.
 * This is the package's native money value: an integer number of minor
 * units (e.g. pence) plus a CurrencyInterface.
 * This object does not use any third-party packages to represent the amount,
 * but can be converted to a moneyphp/money instance with toMoney() if that
 * package is installed.
 */

class Amount implements AmountInterface
{
    protected int $amount;

    /**
     * @param CurrencyInterface $currency
     * @param int|string $amount Minor unit total amount, with no decimal part
     */
    public function __construct(
        protected readonly CurrencyInterface $currency,
        int|string $amount = 0
    ) {
        $this->setMinorUnit($amount);
    }

    /**
     * Allow the decimal notation of the currency to be supplied,
     * as a float or a string.
     *
     * @param float|string|int $amount Total amount as major units and fractions of major units
     *
     * @return self Clone of $this with a new amount set
     */
    public function withMajorUnit(float|string|int $amount): self
    {
        if (is_int($amount) || is_float($amount) || (is_string($amount) && preg_match('/^[0-9]*\.[0-9]*$/', $amount))) {
            $calculatedAmount = (float)$amount * pow(10, $this->currency->getDigits());

            if (floor($calculatedAmount) != round($calculatedAmount, 6)) {
                // Too many decimal digits for the currency.
                throw new UnexpectedValueException(sprintf(
                    'Amount has too many decimal places. Calculated minor unit %f should be an integer.',
                    $calculatedAmount
                ));
            }

            $clone = clone $this;
            $clone->setMinorUnit((int)$calculatedAmount);
            return $clone;
        }

        throw new UnexpectedValueException('Major Unit must be a number.');
    }

    /**
     * Set the minor unit.
     *
     * @param int|string $amount An amount in minor units, with no decimal part
     */
    protected function setMinorUnit(int|string $amount): void
    {
        if (is_int($amount) || (is_string($amount) && preg_match('/^[0-9]+$/', $amount))) {
            $this->amount = (int)$amount;
        } else {
            throw new UnexpectedValueException('Amount is an unexpected data type.');
        }
    }

    /**
     * Allow the smallest units of the currency to be supplied
     * as an integer or a string.
     *
     * @param int|string $amount An amount in minor units, with no decimal part
     */
    public function withMinorUnit(int|string $amount): self
    {
        $clone = clone $this;
        $clone->setMinorUnit($amount);
        return $clone;
    }

    /**
     * Magic method to support e.g. $amount = Amount::EUR(995)
     * equivalent to: new Amount(new Currency('EUR'), 995)
     *
     * @param string $name The three-letter ISO currency code
     * @param array $arguments [0] = required amount
     *
     * @throws Exception
     */
    public static function __callStatic(string $name, array $arguments): static
    {
        try {
            $currency = new Currency($name);
        } catch (UnexpectedValueException $e) {
            $trace = debug_backtrace();
            throw new Exception(sprintf(
                'Call to undefined method %s::%s() in %s on line %d',
                get_called_class(),
                $name,
                $trace[0]['file'],
                $trace[0]['line']
            ));
        }

        return isset($arguments[0])
            ? new static($currency, $arguments[0])
            : new static($currency);
    }

    /**
     * The amount in integer minor units, e.g. 999 for £9.99.
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * The currency this amount is in.
     */
    public function getCurrency(): CurrencyInterface
    {
        return $this->currency;
    }

    /**
     * The ISO 4217 three-character currency code, e.g. "GBP".
     */
    public function getCurrencyCode(): string
    {
        return $this->currency->getCode();
    }

    /**
     * Convert to a moneyphp/money instance.
     * Requires the optional moneyphp/money package to be installed.
     *
     * @throws \RuntimeException if moneyphp/money is not installed
     */
    public function toMoney(): Money
    {
        return MoneyAmount::fromAmount($this)->toMoney();
    }
}
