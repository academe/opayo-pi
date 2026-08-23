<?php

namespace Academe\Opayo\Pi\Money;

use PHPUnit\Framework\TestCase;

class AmountTest extends TestCase
{
    public function testConstructWithMinorUnits()
    {
        $currency = new Currency('GBP');
        $amount = new Amount($currency, 999);

        $this->assertEquals(999, $amount->getAmount());
        $this->assertEquals('GBP', $amount->getCurrencyCode());
    }

    public function testWithMinorUnit()
    {
        $currency = new Currency('GBP');
        $amount = new Amount($currency, 100);
        $newAmount = $amount->withMinorUnit(999);

        // Original should be unchanged
        $this->assertEquals(100, $amount->getAmount());

        // New instance should have new amount
        $this->assertEquals(999, $newAmount->getAmount());

        // Should be different instances
        $this->assertNotSame($amount, $newAmount);
    }

    public function testWithMajorUnitInteger()
    {
        $currency = new Currency('GBP');
        $amount = new Amount($currency);
        $newAmount = $amount->withMajorUnit(10);

        $this->assertEquals(1000, $newAmount->getAmount());
    }

    public function testWithMajorUnitFloat()
    {
        $currency = new Currency('GBP');
        $amount = new Amount($currency);
        $newAmount = $amount->withMajorUnit(9.99);

        $this->assertEquals(999, $newAmount->getAmount());
    }

    public function testWithMajorUnitString()
    {
        $currency = new Currency('GBP');
        $amount = new Amount($currency);
        $newAmount = $amount->withMajorUnit('12.50');

        $this->assertEquals(1250, $newAmount->getAmount());
    }

    public function testWithMajorUnitTooManyDecimals()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/too many decimal places/i');

        $currency = new Currency('GBP');
        $amount = new Amount($currency);
        $amount->withMajorUnit(9.999); // 3 decimal places for a 2-digit currency
    }

    public function testWithMajorUnitInvalidString()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/must be a number/i');

        $currency = new Currency('GBP');
        $amount = new Amount($currency);
        $amount->withMajorUnit('invalid');
    }

    public function testStaticCurrencyMethod()
    {
        $amount = Amount::GBP(999);

        $this->assertEquals(999, $amount->getAmount());
        $this->assertEquals('GBP', $amount->getCurrencyCode());
    }

    public function testStaticCurrencyMethodUSD()
    {
        $amount = Amount::USD(1234);

        $this->assertEquals(1234, $amount->getAmount());
        $this->assertEquals('USD', $amount->getCurrencyCode());
    }

    public function testStaticCurrencyMethodEUR()
    {
        $amount = Amount::EUR(500);

        $this->assertEquals(500, $amount->getAmount());
        $this->assertEquals('EUR', $amount->getCurrencyCode());
    }

    public function testStaticCurrencyMethodWithoutAmount()
    {
        $amount = Amount::GBP();

        $this->assertEquals(0, $amount->getAmount());
        $this->assertEquals('GBP', $amount->getCurrencyCode());
    }

    public function testGetCurrency()
    {
        $currency = new Currency('GBP');
        $amount = new Amount($currency, 999);

        $this->assertInstanceOf(CurrencyInterface::class, $amount->getCurrency());
        $this->assertEquals('GBP', $amount->getCurrency()->getCode());
    }

    public function testValidMinorUnitString()
    {
        $currency = new Currency('GBP');
        $amount = new Amount($currency, '999');

        $this->assertEquals(999, $amount->getAmount());
    }

    public function testInvalidMinorUnitString()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/unexpected data type/i');

        $currency = new Currency('GBP');
        new Amount($currency, '9.99'); // String with decimal
    }

    /**
     * @dataProvider majorUnitStringProvider
     */
    public function testWithMajorUnitAcceptsIntegerAndDecimalStrings(string $input, int $expectedMinor)
    {
        $this->assertSame($expectedMinor, Amount::GBP()->withMajorUnit($input)->getAmount());
    }

    public static function majorUnitStringProvider(): array
    {
        return [
            'integer string' => ['10', 1000],
            'trailing point' => ['10.', 1000],
            'leading point' => ['.50', 50],
            'two decimals' => ['12.50', 1250],
            'zero' => ['0', 0],
        ];
    }

    public function testWithMajorUnitRejectsBarePoint()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/must be a number/i');

        Amount::GBP()->withMajorUnit('.');
    }
}
