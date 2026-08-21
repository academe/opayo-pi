<?php

namespace Academe\Opayo\Pi\Money;

use Money\Currency as MoneyCurrency;
use Money\Money;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the moneyphp/money bridge: MoneyAmount (in) and toMoney() (out).
 */
class MoneyAmountTest extends TestCase
{
    public function testImplementsAmountInterface()
    {
        $amount = new MoneyAmount(Money::GBP(999));

        $this->assertInstanceOf(AmountInterface::class, $amount);
    }

    public function testGetAmountReturnsIntegerMinorUnits()
    {
        $amount = new MoneyAmount(Money::GBP(999));

        $this->assertSame(999, $amount->getAmount());
        $this->assertSame('GBP', $amount->getCurrencyCode());
    }

    public function testZeroDecimalCurrency()
    {
        $amount = new MoneyAmount(new Money(1500, new MoneyCurrency('JPY')));

        $this->assertSame(1500, $amount->getAmount());
        $this->assertSame('JPY', $amount->getCurrencyCode());
    }

    public function testToMoneyReturnsWrappedInstance()
    {
        $money = Money::EUR(500);
        $amount = new MoneyAmount($money);

        $this->assertSame($money, $amount->toMoney());
    }

    public function testFromAmountConvertsNativeAmount()
    {
        $native = Amount::USD(1234);
        $amount = MoneyAmount::fromAmount($native);

        $this->assertInstanceOf(MoneyAmount::class, $amount);
        $this->assertSame(1234, $amount->getAmount());
        $this->assertSame('USD', $amount->getCurrencyCode());
        $this->assertTrue($amount->toMoney()->equals(Money::USD(1234)));
    }

    public function testFromAmountReturnsSameInstanceForMoneyAmount()
    {
        $amount = new MoneyAmount(Money::GBP(1));

        $this->assertSame($amount, MoneyAmount::fromAmount($amount));
    }

    public function testFromAmountAcceptsAnyAmountInterface()
    {
        $custom = new class implements AmountInterface {
            public function getAmount(): int
            {
                return 4200;
            }

            public function getCurrencyCode(): string
            {
                return 'CHF';
            }
        };

        $money = MoneyAmount::fromAmount($custom)->toMoney();

        $this->assertTrue($money->equals(new Money(4200, new MoneyCurrency('CHF'))));
    }

    public function testNativeAmountToMoney()
    {
        $money = Amount::GBP(999)->toMoney();

        $this->assertInstanceOf(Money::class, $money);
        $this->assertSame('999', $money->getAmount());
        $this->assertSame('GBP', $money->getCurrency()->getCode());
    }

    public function testNativeAmountToMoneyFromMajorUnit()
    {
        $money = Amount::GBP()->withMajorUnit('12.50')->toMoney();

        $this->assertTrue($money->equals(Money::GBP(1250)));
    }

    public function testRoundTrip()
    {
        $original = Money::GBP(999);

        $roundTripped = Amount::GBP((new MoneyAmount($original))->getAmount())->toMoney();

        $this->assertTrue($original->equals($roundTripped));
    }
}
