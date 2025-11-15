<?php

namespace Academe\Opayo\Pi\Request\Model;

use PHPUnit\Framework\TestCase;

class ApplePayPaymentTest extends TestCase
{
    protected string $clientIp = '192.168.1.100';
    protected string $payload = 'base64EncodedApplePayTokenHere==';
    protected string $sessionValidationToken = 'sessionToken123';

    public function testConstructWithoutSessionToken()
    {
        $payment = new ApplePayPayment($this->clientIp, $this->payload);

        $data = $payment->jsonSerialize();

        $this->assertArrayHasKey('applePay', $data);
        $this->assertEquals($this->clientIp, $data['applePay']['clientIpAddress']);
        $this->assertEquals($this->payload, $data['applePay']['payload']);
        $this->assertArrayNotHasKey('sessionValidationToken', $data['applePay']);
    }

    public function testConstructWithSessionToken()
    {
        $payment = new ApplePayPayment(
            $this->clientIp,
            $this->payload,
            $this->sessionValidationToken
        );

        $data = $payment->jsonSerialize();

        $this->assertArrayHasKey('applePay', $data);
        $this->assertEquals($this->clientIp, $data['applePay']['clientIpAddress']);
        $this->assertEquals($this->payload, $data['applePay']['payload']);
        $this->assertArrayHasKey('sessionValidationToken', $data['applePay']);
        $this->assertEquals($this->sessionValidationToken, $data['applePay']['sessionValidationToken']);
    }

    public function testGetters()
    {
        $payment = new ApplePayPayment(
            $this->clientIp,
            $this->payload,
            $this->sessionValidationToken
        );

        $this->assertEquals($this->clientIp, $payment->getClientIpAddress());
        $this->assertEquals($this->payload, $payment->getPayload());
        $this->assertEquals($this->sessionValidationToken, $payment->getSessionValidationToken());
    }

    public function testGetSessionValidationTokenReturnsNull()
    {
        $payment = new ApplePayPayment($this->clientIp, $this->payload);

        $this->assertNull($payment->getSessionValidationToken());
    }

    public function testWithSessionValidationToken()
    {
        $payment = new ApplePayPayment($this->clientIp, $this->payload);
        $paymentWithToken = $payment->withSessionValidationToken($this->sessionValidationToken);

        // Original should not have token
        $originalData = $payment->jsonSerialize();
        $this->assertArrayNotHasKey('sessionValidationToken', $originalData['applePay']);

        // Cloned payment should have token
        $clonedData = $paymentWithToken->jsonSerialize();
        $this->assertArrayHasKey('sessionValidationToken', $clonedData['applePay']);
        $this->assertEquals($this->sessionValidationToken, $clonedData['applePay']['sessionValidationToken']);

        // Should be different instances (immutability)
        $this->assertNotSame($payment, $paymentWithToken);
    }

    public function testFromDataWithJsonString()
    {
        $json = json_encode([
            'applePay' => [
                'clientIpAddress' => $this->clientIp,
                'payload' => $this->payload,
                'sessionValidationToken' => $this->sessionValidationToken,
            ]
        ]);

        $payment = ApplePayPayment::fromData($json);
        $data = $payment->jsonSerialize();

        $this->assertEquals($this->clientIp, $data['applePay']['clientIpAddress']);
        $this->assertEquals($this->payload, $data['applePay']['payload']);
        $this->assertEquals($this->sessionValidationToken, $data['applePay']['sessionValidationToken']);
    }

    public function testFromDataWithArray()
    {
        $arrayData = [
            'applePay' => [
                'clientIpAddress' => $this->clientIp,
                'payload' => $this->payload,
            ]
        ];

        $payment = ApplePayPayment::fromData($arrayData);
        $data = $payment->jsonSerialize();

        $this->assertEquals($this->clientIp, $data['applePay']['clientIpAddress']);
        $this->assertEquals($this->payload, $data['applePay']['payload']);
        $this->assertArrayNotHasKey('sessionValidationToken', $data['applePay']);
    }

    public function testFromDataWithoutApplePayWrapper()
    {
        $arrayData = [
            'clientIpAddress' => $this->clientIp,
            'payload' => $this->payload,
            'sessionValidationToken' => $this->sessionValidationToken,
        ];

        $payment = ApplePayPayment::fromData($arrayData);
        $data = $payment->jsonSerialize();

        $this->assertEquals($this->clientIp, $data['applePay']['clientIpAddress']);
        $this->assertEquals($this->payload, $data['applePay']['payload']);
        $this->assertEquals($this->sessionValidationToken, $data['applePay']['sessionValidationToken']);
    }

    public function testImplementsPaymentMethodInterface()
    {
        $payment = new ApplePayPayment($this->clientIp, $this->payload);

        $this->assertInstanceOf(PaymentMethodInterface::class, $payment);
    }
}
