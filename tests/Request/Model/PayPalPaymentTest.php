<?php

namespace Academe\Opayo\Pi\Request\Model;

use PHPUnit\Framework\TestCase;

/**
 * paymentMethod.paypal wire format, per the Opayo Pi API reference:
 * merchantSessionKey and callbackUrl only. (The PayPal order ID comes back
 * in the Redirect response, not in the request.)
 */
class PayPalPaymentTest extends TestCase
{
    protected string $msk = 'MSK-0123456789';
    protected string $callbackUrl = 'https://shop.example.com/paypal-return';

    public function testSerialisation()
    {
        $payment = new PayPalPayment($this->msk, $this->callbackUrl);

        $this->assertSame([
            'paypal' => [
                'merchantSessionKey' => $this->msk,
                'callbackUrl' => $this->callbackUrl,
            ],
        ], $payment->jsonSerialize());
    }

    public function testGetters()
    {
        $payment = new PayPalPayment($this->msk, $this->callbackUrl);

        $this->assertSame($this->msk, $payment->getMerchantSessionKey());
        $this->assertSame($this->callbackUrl, $payment->getCallbackUrl());
    }

    public function testFromDataRoundTrip()
    {
        $original = new PayPalPayment($this->msk, $this->callbackUrl);

        $this->assertSame(
            $original->jsonSerialize(),
            PayPalPayment::fromData(json_encode($original->jsonSerialize()))->jsonSerialize()
        );
    }

    public function testFromDataWithoutWrapper()
    {
        $payment = PayPalPayment::fromData(['merchantSessionKey' => $this->msk, 'callbackUrl' => $this->callbackUrl]);

        $this->assertSame($this->callbackUrl, $payment->getCallbackUrl());
    }

    public function testImplementsPaymentMethodInterface()
    {
        $this->assertInstanceOf(PaymentMethodInterface::class, new PayPalPayment($this->msk, $this->callbackUrl));
    }
}
