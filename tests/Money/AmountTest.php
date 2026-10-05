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

    /**
     * These prices are not exact in binary floating point: multiplied by 100
     * they land a hair under the whole number (19.99 gives 1998.9999999999998).
     *
     * @dataProvider inexactFloatProvider
     */
    public function testWithMajorUnitAcceptsPricesThatAreInexactAsFloats(string $input, int $expectedMinor)
    {
        $this->assertSame($expectedMinor, Amount::GBP()->withMajorUnit($input)->getAmount());
        $this->assertSame($expectedMinor, Amount::GBP()->withMajorUnit((float) $input)->getAmount());
    }

    public static function inexactFloatProvider(): array
    {
        return [
            '19.99' => ['19.99', 1999],
            '0.29' => ['0.29', 29],
            '4.35' => ['4.35', 435],
            '1.15' => ['1.15', 115],
        ];
    }

    public function testWithMajorUnitAcceptsEveryPriceUpToAThousand()
    {
        $failures = [];

        for ($minor = 0; $minor < 100000; $minor++) {
            $price = sprintf('%d.%02d', intdiv($minor, 100), $minor % 100);

            foreach ([$price, (float) $price] as $input) {
                try {
                    $actual = Amount::GBP()->withMajorUnit($input)->getAmount();
                } catch (\UnexpectedValueException $e) {
                    $actual = 'exception';
                }

                if ($actual !== $minor) {
                    $failures[] = sprintf('%s (%s) gave %s', $price, gettype($input), $actual);
                }
            }
        }

        $this->assertSame([], array_slice($failures, 0, 10), count($failures) . ' prices failed');
    }

    public function testWithMajorUnitStringIsExactForLargeAmounts()
    {
        $this->assertSame(1234567890123, Amount::GBP()->withMajorUnit('12345678901.23')->getAmount());
    }

    public function testWithMajorUnitStringAllowsTrailingZeros()
    {
        $this->assertSame(1050, Amount::GBP()->withMajorUnit('10.500')->getAmount());
    }

    public function testWithMajorUnitStringTooManyDecimals()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/too many decimal places/i');

        Amount::GBP()->withMajorUnit('19.999');
    }

    public function testWithMajorUnitForCurrencyWithoutMinorUnits()
    {
        $this->assertSame(500, Amount::JPY()->withMajorUnit('500')->getAmount());
        $this->assertSame(500, Amount::JPY()->withMajorUnit('500.0')->getAmount());
        $this->assertSame(500, Amount::JPY()->withMajorUnit(500.0)->getAmount());
    }

    public function testWithMajorUnitRejectsFractionForCurrencyWithoutMinorUnits()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/too many decimal places/i');

        Amount::JPY()->withMajorUnit('500.5');
    }

    public function testWithMajorUnitForCurrencyWithThreeMinorUnits()
    {
        $this->assertSame(12000, Amount::KWD()->withMajorUnit('12')->getAmount());
        $this->assertSame(12500, Amount::KWD()->withMajorUnit('12.5')->getAmount());
        $this->assertSame(12345, Amount::KWD()->withMajorUnit('12.345')->getAmount());
        $this->assertSame(12345, Amount::KWD()->withMajorUnit(12.345)->getAmount());
        $this->assertSame(12000, Amount::KWD()->withMajorUnit(12)->getAmount());
    }

    public function testWithMajorUnitRejectsFourthDecimalForCurrencyWithThreeMinorUnits()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/too many decimal places/i');

        Amount::KWD()->withMajorUnit('12.3456');
    }

    public function testWithMajorUnitRejectsBarePoint()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/must be a number/i');

        Amount::GBP()->withMajorUnit('.');
    }
}
