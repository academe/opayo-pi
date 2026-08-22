<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response;

use Academe\Opayo\Pi\Factory\ResponseFactory;
use PHPUnit\Framework\TestCase;

class PayPalRedirectTest extends TestCase
{
    /**
     * Exactly what the Opayo sandbox returns for a PayPal payment request.
     */
    protected function data(): array
    {
        return [
            'transactionId' => '471e3b7e-eac7-4c56-a2bb-0ef5d6d55992',
            'transactionType' => 'Payment',
            'status' => 'Redirect',
            'statusCode' => '2023',
            'statusDetail' => 'Transaction registered, redirect client to wallet server.',
            'paymentMethod' => [
                'paypal' => [
                    'redirectUrl' => 'https://www.sandbox.paypal.com/checkoutnow?token=43196520EB311462U',
                    'orderId' => '43196520EB311462U',
                ],
            ],
        ];
    }

    public function testParses()
    {
        $redirect = PayPalRedirect::fromData($this->data(), 201);

        $this->assertSame('471e3b7e-eac7-4c56-a2bb-0ef5d6d55992', $redirect->getTransactionId());
        $this->assertSame('Payment', $redirect->getTransactionType());
        $this->assertSame('Redirect', $redirect->getStatus());
        $this->assertSame('2023', $redirect->getStatusCode());
        $this->assertSame(
            'https://www.sandbox.paypal.com/checkoutnow?token=43196520EB311462U',
            $redirect->getRedirectUrl()
        );
        $this->assertSame('43196520EB311462U', $redirect->getOrderId());
        $this->assertSame(201, $redirect->getHttpCode());
    }

    public function testIsRedirectNotSuccessNotFinal()
    {
        $redirect = PayPalRedirect::fromData($this->data());

        $this->assertTrue($redirect->isRedirect());
        $this->assertFalse($redirect->isSuccessful());
        $this->assertSame(TransactionStatus::REDIRECT, $redirect->getStatusEnum());
        $this->assertFalse($redirect->getStatusEnum()->isFinal());
        $this->assertFalse($redirect->getStatusEnum()->isError());
        $this->assertSame('info', $redirect->getStatusEnum()->severity());
        $this->assertSame(AbstractTransaction::STATUS_REDIRECT, TransactionStatus::REDIRECT->value);
    }

    public function testFactoryPicksRedirectBeforeGenericPayment()
    {
        $this->assertInstanceOf(PayPalRedirect::class, ResponseFactory::fromData($this->data(), 201));
    }

    public function testFactoryStillMapsOrdinaryPayment()
    {
        $payment = [
            'statusCode' => '0000',
            'statusDetail' => 'The Authorisation was Successful.',
            'transactionId' => 'D6A8A7BB-C136-1FE4-7FB5-D61F3846176D',
            'transactionType' => 'Payment',
            'status' => 'Ok',
            'paymentMethod' => ['card' => ['cardType' => 'Visa', 'lastFourDigits' => '0006', 'expiryDate' => '0828']],
            'amount' => ['totalAmount' => 999, 'saleAmount' => 999, 'surchargeAmount' => 0],
            'currency' => 'GBP',
        ];

        $response = ResponseFactory::fromData($payment, 201);

        $this->assertInstanceOf(Payment::class, $response);
        $this->assertNotInstanceOf(PayPalRedirect::class, $response);
    }

    public function testIsResponseGuard()
    {
        $this->assertTrue(PayPalRedirect::isResponse($this->data()));
        $this->assertTrue(PayPalRedirect::isResponse(json_decode(json_encode($this->data()))));
        $this->assertFalse(PayPalRedirect::isResponse(['statusCode' => '2023']));
        $this->assertFalse(PayPalRedirect::isResponse(['statusCode' => '0000', 'paymentMethod' => ['paypal' => []]]));
        $this->assertFalse(PayPalRedirect::isResponse(null));
        $this->assertFalse(PayPalRedirect::isResponse('string'));
    }

    public function testJsonRoundTrip()
    {
        $redirect = PayPalRedirect::fromData($this->data());

        $this->assertEquals($redirect, PayPalRedirect::fromData(json_encode($redirect)));
        $this->assertSame($this->data(), $redirect->jsonSerialize());
    }
}
