<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Money\AmountInterface;
use Academe\Opayo\Pi\Money\MoneyAmount;
use Academe\Opayo\Pi\Request\Model\Address;
use Academe\Opayo\Pi\Request\Model\PaymentMethodInterface;
use Academe\Opayo\Pi\Request\Model\Person;
use Money\Money;
use PHPUnit\Framework\TestCase;

/**
 * Every request that takes an amount must accept the package's own
 * AmountInterface and a moneyphp/money Money instance interchangeably,
 * producing the same wire data.
 */
class AmountParameterTest extends TestCase
{
    /**
     * @return array<string, array{AmountInterface|Money}>
     */
    public static function amountProvider(): array
    {
        return [
            'native Amount' => [Amount::GBP(999)],
            'MoneyAmount wrapper' => [new MoneyAmount(Money::GBP(999))],
            'Money\Money directly' => [Money::GBP(999)],
        ];
    }

    protected function endpoint(): Endpoint
    {
        return new Endpoint(Endpoint::MODE_TEST);
    }

    protected function auth(): Auth
    {
        return new Auth('vendor', 'key', 'password');
    }

    protected function paymentMethod(): PaymentMethodInterface
    {
        return new class implements PaymentMethodInterface {
            public function jsonSerialize(): mixed
            {
                return ['card' => ['merchantSessionKey' => 'key', 'cardIdentifier' => 'id']];
            }
        };
    }

    /**
     * @dataProvider amountProvider
     */
    public function testCreatePayment(AmountInterface|Money $amount)
    {
        $body = (new CreatePayment(
            $this->endpoint(),
            $this->auth(),
            $this->paymentMethod(),
            'vendor-tx-code',
            $amount,
            'Description',
            new Address('Billing1', 'Billing2', 'BillCity', 'NE26 2SB', 'GB'),
            new Person('Bill', 'Payer'),
        ))->jsonSerialize();

        $this->assertSame(999, $body['amount']);
        $this->assertSame('GBP', $body['currency']);
    }

    /**
     * @dataProvider amountProvider
     */
    public function testCreateDeferred(AmountInterface|Money $amount)
    {
        $body = (new CreateDeferred(
            $this->endpoint(),
            $this->auth(),
            $this->paymentMethod(),
            'vendor-tx-code',
            $amount,
            'Description',
            new Address('Billing1', 'Billing2', 'BillCity', 'NE26 2SB', 'GB'),
            new Person('Bill', 'Payer'),
        ))->jsonSerialize();

        $this->assertSame('Deferred', $body['transactionType']);
        $this->assertSame(999, $body['amount']);
        $this->assertSame('GBP', $body['currency']);
    }

    /**
     * @dataProvider amountProvider
     */
    public function testCreateRepeatPayment(AmountInterface|Money $amount)
    {
        $body = (new CreateRepeatPayment(
            $this->endpoint(),
            $this->auth(),
            'REF-TRANSACTION-ID',
            'vendor-tx-code',
            $amount,
            'Description',
        ))->jsonSerialize();

        $this->assertSame(999, $body['amount']);
        $this->assertSame('GBP', $body['currency']);
    }

    /**
     * @dataProvider amountProvider
     */
    public function testCreateRefund(AmountInterface|Money $amount)
    {
        $body = (new CreateRefund(
            $this->endpoint(),
            $this->auth(),
            'REF-TRANSACTION-ID',
            'vendor-tx-code',
            $amount,
            'Description',
        ))->jsonSerialize();

        $this->assertSame(999, $body['amount']);
    }

    /**
     * @dataProvider amountProvider
     */
    public function testCreateRelease(AmountInterface|Money $amount)
    {
        $body = (new CreateRelease(
            $this->endpoint(),
            $this->auth(),
            'TRANSACTION-ID',
            $amount,
        ))->jsonSerialize();

        $this->assertSame(999, $body['amount']);
    }
}
