<?php

namespace Academe\Opayo\Pi\Request\Model;

use PHPUnit\Framework\TestCase;

/**
 * paymentMethod.googlePay wire format, per the Opayo Pi API reference:
 * merchantSessionKey, clientIpAddress, payload (base64 of the Google token).
 */
class GooglePayPaymentTest extends TestCase
{
    protected string $msk = 'MSK-0123456789';
    protected string $clientIp = '192.168.1.100';
    protected string $payload = 'base64EncodedGooglePayTokenHere==';

    public function testSerialisation()
    {
        $payment = new GooglePayPayment($this->msk, $this->clientIp, $this->payload);

        $this->assertSame([
            'googlePay' => [
                'merchantSessionKey' => $this->msk,
                'clientIpAddress' => $this->clientIp,
                'payload' => $this->payload,
            ],
        ], $payment->jsonSerialize());
    }

    public function testGetters()
    {
        $payment = new GooglePayPayment($this->msk, $this->clientIp, $this->payload);

        $this->assertSame($this->msk, $payment->getMerchantSessionKey());
        $this->assertSame($this->clientIp, $payment->getClientIpAddress());
        $this->assertSame($this->payload, $payment->getPayload());
    }

    public function testFromGoogleTokenBase64Encodes()
    {
        // paymentData.paymentMethodData.tokenizationData.token is itself a JSON string.
        $token = '{"signature":"MEUC...","protocolVersion":"ECv2","signedMessage":"{...}"}';

        $payment = GooglePayPayment::fromGoogleToken($this->msk, $this->clientIp, $token);

        $this->assertSame(base64_encode($token), $payment->getPayload());
        $this->assertSame($token, base64_decode($payment->jsonSerialize()['googlePay']['payload']));
    }

    public function testFromDataRoundTrip()
    {
        $original = new GooglePayPayment($this->msk, $this->clientIp, $this->payload);

        $this->assertSame(
            $original->jsonSerialize(),
            GooglePayPayment::fromData(json_encode($original->jsonSerialize()))->jsonSerialize()
        );
    }

    public function testFromDataWithoutWrapper()
    {
        $payment = GooglePayPayment::fromData([
            'merchantSessionKey' => $this->msk,
            'clientIpAddress' => $this->clientIp,
            'payload' => $this->payload,
        ]);

        $this->assertSame($this->msk, $payment->getMerchantSessionKey());
        $this->assertSame($this->payload, $payment->getPayload());
    }

    public function testImplementsPaymentMethodInterface()
    {
        $this->assertInstanceOf(
            PaymentMethodInterface::class,
            new GooglePayPayment($this->msk, $this->clientIp, $this->payload)
        );
    }
}
