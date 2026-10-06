<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response\Model;

use Academe\Opayo\Pi\Factory\ResponseFactory;
use PHPUnit\Framework\TestCase;

class PayPalTest extends TestCase
{
    /**
     * The paymentMethod.paypal object of a completed PayPal transaction, as
     * returned by the sandbox when the transaction was fetched on the
     * shopper's return (October 2026).
     */
    protected array $completed = [
        'orderId' => '15V76915E2502383G',
        'payerId' => 'WK4H3PY3AM7MN',
        'captureId' => '13H72727UG896832N',
    ];

    public function testCompletedPaymentHasPayerAndCaptureIds()
    {
        $paypal = PayPal::fromData($this->completed);

        $this->assertSame('15V76915E2502383G', $paypal->getOrderId());
        $this->assertSame('WK4H3PY3AM7MN', $paypal->getPayerId());
        $this->assertSame('13H72727UG896832N', $paypal->getCaptureId());
        $this->assertNull($paypal->getRedirectUrl());
    }

    public function testRedirectResponseHasNeither()
    {
        $paypal = PayPal::fromData(['paypal' => [
            'redirectUrl' => 'https://www.sandbox.paypal.com/checkoutnow?token=43196520EB311462U',
            'orderId' => '43196520EB311462U',
        ]]);

        $this->assertSame('43196520EB311462U', $paypal->getOrderId());
        $this->assertNull($paypal->getPayerId());
        $this->assertNull($paypal->getCaptureId());
        $this->assertSame(
            ['redirectUrl' => 'https://www.sandbox.paypal.com/checkoutnow?token=43196520EB311462U', 'orderId' => '43196520EB311462U'],
            $paypal->jsonSerialize()
        );
    }

    public function testSerializesAllThreeIds()
    {
        $this->assertSame($this->completed, PayPal::fromData($this->completed)->jsonSerialize());
    }

    public function testExistingConstructorArgumentsKeepTheirPositions()
    {
        $paypal = new PayPal('ORDER', 'https://example.com/redirect');

        $this->assertSame('ORDER', $paypal->getOrderId());
        $this->assertSame('https://example.com/redirect', $paypal->getRedirectUrl());
    }

    public function testFetchedTransactionExposesThem()
    {
        $payment = ResponseFactory::fromData([
            'statusCode' => '0000',
            'statusDetail' => 'The Authorisation was Successful.',
            'transactionId' => 'T-PAYPAL',
            'transactionType' => 'Payment',
            'paymentMethod' => ['paypal' => $this->completed],
            'amount' => ['totalAmount' => 999, 'saleAmount' => 999, 'surchargeAmount' => 0],
            'currency' => 'GBP',
            'status' => 'Ok',
        ], 200);

        $this->assertSame('WK4H3PY3AM7MN', $payment->getPayPal()->getPayerId());
        $this->assertSame('13H72727UG896832N', $payment->getPayPal()->getCaptureId());
        $this->assertSame($this->completed, $payment->jsonSerialize()['paymentMethod']['paypal']);
    }
}
