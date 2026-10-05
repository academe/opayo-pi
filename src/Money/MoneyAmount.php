<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Money;

use Money\Currency as MoneyCurrency;
use Money\Money;
use RuntimeException;

/**
 * Value object for the amount, wrapping a moneyphp/money Money instance.
 *
 * This is the bridge for applications that already use moneyphp/money:
 *
 *   - In:  the request constructors (CreatePayment, CreateRefund, etc.) accept a
 *          Money instance directly and wrap it in this class internally;
 *          new MoneyAmount(Money::GBP(999)) does the same explicitly wherever an
 *          AmountInterface is needed.
 *   - Out: MoneyAmount::fromAmount($response->getTotalAmount())->toMoney() turns
 *          any AmountInterface (native Amount, a response amount, or a custom
 *          implementation) back into a Money instance.
 *
 * moneyphp/money (^3.0 or ^4.0) is an optional package, so must be required
 * manually if you want to use this class. A RuntimeException is thrown from
 * fromAmount() if it is not installed; the constructor cannot be reached without
 * it, since a Money instance is required.
 */

class MoneyAmount implements AmountInterface
{
    public function __construct(protected readonly Money $money)
    {
    }

    /**
     * Convert any AmountInterface into a MoneyAmount, so that toMoney()
     * can be called on it. A MoneyAmount is returned as-is.
     *
     * @param AmountInterface $amount Any amount, e.g. from a transaction response
     * @return static
     * @throws RuntimeException if moneyphp/money is not installed
     */
    public static function fromAmount(AmountInterface $amount): static
    {
        if ($amount instanceof static) {
            return $amount;
        }

        static::assertMoneyAvailable();

        return new static(new Money(
            $amount->getAmount(),
            new MoneyCurrency($amount->getCurrencyCode())
        ));
    }

    /**
     * The amount in integer minor units, e.g. 999 for £9.99.
     * Money::getAmount() returns a numeric string, so it is cast here.
     */
    public function getAmount(): int
    {
        return (int)$this->money->getAmount();
    }

    /**
     * The ISO 4217 three-character currency code, e.g. "GBP".
     */
    public function getCurrencyCode(): string
    {
        return $this->money->getCurrency()->getCode();
    }

    /**
     * The wrapped moneyphp/money instance.
     */
    public function toMoney(): Money
    {
        return $this->money;
    }

    /**
     * Guard for code paths that construct a Money instance, so that a missing
     * optional package gives a clear message rather than a class-not-found error.
     *
     * @throws RuntimeException
     */
    protected static function assertMoneyAvailable(): void
    {
        if (! class_exists(Money::class)) {
            throw new RuntimeException(
                'The moneyphp/money package is not installed;'
                . ' run "composer require moneyphp/money" to use Money conversion.'
            );
        }
    }
}
