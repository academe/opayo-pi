<?php

namespace Academe\Opayo\Pi\Money;

/**
 * Value object for the amount, in the appropriate currency.
 * This object does not use any third-party packages to represent the amount.
 */

use UnexpectedValueException;
use Exception;

class Amount implements AmountInterface
{
    /**
     * @var Integer value in the smallest units
     */
    protected $amount;
    protected $currency;

    /**
     * @param Academe\Opayo\Pi\Money\Currency|Currency $currency
     * @param int $amount Minor unit total amount, with no decimal part
     */
    public function __construct(CurrencyInterface $currency, $amount = 0)
    {
        $this->currency = $currency;
        $this->setMinorUnit($amount);
    }

    /**
     * Allow the decimal notation of the currency to be supplied,
     * as a float or a string.
     *
     * @param float|string|int $amount Total amount as major units and fractions of major units
     *
     * @return Amount Clone of $this with a newamount set
     */
    public function withMajorUnit($amount)
    {
        if (is_int($amount) || is_float($amount) || (is_string($amount) && preg_match('/^[0-9]*\.[0-9]*$/', $amount))) {
            $amount = (float)$amount * pow(10, $this->currency->getDigits());

            if (floor($amount) != round($amount, 6)) {
                // Too many decimal digits for the currency.
                throw new UnexpectedValueException(sprintf(
                    'Amount has too many decimal places. Calculated minor unit %f should be an integer.',
                    $amount
                ));
            }

            $clone = clone $this;
            $clone->setMinorUnit((int)$amount);
            return $clone;
        } else {
            throw new UnexpectedValueException(sprintf(
                'Major Unit must be a number.'
            ));
        }
    }

    /**
     * Set the minot unit.
     *
     * @param int|string $amount An amount in minor units, with no decimal part
     */
    protected function setMinorUnit($amount)
    {
        if (is_int($amount) || (is_string($amount) && preg_match('/^[0-9]+$/', $amount))) {
            $this->amount = (int)$amount;
        } else {
            throw new UnexpectedValueException(sprintf(
                'Amount is an unexpected data type.'
            ));
        }
    }

    /**
     * Allow the smallest units of the currency to be supplied
     * as an integer or a string.
     *
     * @param int|string $amount An amount in minor units, with no decimal part
     * @return Amount
     */
    public function withMinorUnit($amount)
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
     * @return static New instance of an Amount
     *
     * @throws Exception
     */
    public static function __callStatic($name, array $arguments)
    {
        try {
            $currency = new Currency($name);
        } catch (UnexpectedValueException $e) {
            $trace = debug_backtrace();
            throw new Exception(sprintf(
                'Call to undefined method $class::%s() in %s on line %d',
                get_called_class(),
                $trace[0]['file'],
                $trace[0]['line']
            ));
        }

        if (isset($arguments[0])) {
            return new static($currency, $arguments[0]);
        } else {
            return new static($currency);
        }
    }

    /**
     * @return int The amount, in minot units
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @return Currency The currency object
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @return string The currency three-character ISO code
     */
    public function getCurrencyCode()
    {
        return $this->currency->getCode();
    }
}
