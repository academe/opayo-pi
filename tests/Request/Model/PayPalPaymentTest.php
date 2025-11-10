<?php

namespace Academe\Opayo\Pi\Request\Model;

use PHPUnit\Framework\TestCase;

class PayPalPaymentTest extends TestCase
{
    protected string $clientIp = '192.168.1.100';
    protected string $paypalOrderId = 'PAYPAL-ORDER-12345';
    protected string $payerId = 'PAYER-ID-67890';

    public function testConstructWithoutPayerId()
    {
        $payment = new PayPalPayment($this->clientIp, $this->paypalOrderId);

        $data = $payment->jsonSerialize();

        $this->assertArrayHasKey('paypal', $data);
        $this->assertEquals($this->clientIp, $data['paypal']['clientIpAddress']);
        $this->assertEquals($this->paypalOrderId, $data['paypal']['paypalOrderId']);
        $this->assertArrayNotHasKey('payerId', $data['paypal']);
    }

    public function testConstructWithPayerId()
    {
        $payment = new PayPalPayment($this->clientIp, $this->paypalOrderId, $this->payerId);

        $data = $payment->jsonSerialize();

        $this->assertArrayHasKey('paypal', $data);
        $this->assertEquals($this->clientIp, $data['paypal']['clientIpAddress']);
        $this->assertEquals($this->paypalOrderId, $data['paypal']['paypalOrderId']);
        $this->assertEquals($this->payerId, $data['paypal']['payerId']);
    }

    public function testGetters()
    {
        $payment = new PayPalPayment($this->clientIp, $this->paypalOrderId, $this->payerId);

        $this->assertEquals($this->clientIp, $payment->getClientIpAddress());
        $this->assertEquals($this->paypalOrderId, $payment->getPaypalOrderId());
        $this->assertEquals($this->payerId, $payment->getPayerId());
    }

    public function testGetPayerIdReturnsNull()
    {
        $payment = new PayPalPayment($this->clientIp, $this->paypalOrderId);

        $this->assertNull($payment->getPayerId());
    }

    public function testWithPayerId()
    {
        $payment = new PayPalPayment($this->clientIp, $this->paypalOrderId);
        $paymentWithPayerId = $payment->withPayerId($this->payerId);

        // Verify immutability - should be different instances
        $this->assertNotSame($payment, $paymentWithPayerId);

        // Original should not have payer ID
        $originalData = $payment->jsonSerialize();
        $this->assertArrayNotHasKey('payerId', $originalData['paypal']);

        // Clone should have payer ID
        $clonedData = $paymentWithPayerId->jsonSerialize();
        $this->assertEquals($this->payerId, $clonedData['paypal']['payerId']);
    }

    public function testJsonSerialize()
    {
        $payment = new PayPalPayment($this->clientIp, $this->paypalOrderId, $this->payerId);

        $data = $payment->jsonSerialize();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('paypal', $data);
        $this->assertIsArray($data['paypal']);
        $this->assertCount(3, $data['paypal']);
    }

    public function testFromDataWithJsonString()
    {
        $json = json_encode([
            'paypal' => [
                'clientIpAddress' => $this->clientIp,
                'paypalOrderId' => $this->paypalOrderId,
                'payerId' => $this->payerId,
            ]
        ]);

        $payment = PayPalPayment::fromData($json);
        $data = $payment->jsonSerialize();

        $this->assertEquals($this->clientIp, $data['paypal']['clientIpAddress']);
        $this->assertEquals($this->paypalOrderId, $data['paypal']['paypalOrderId']);
        $this->assertEquals($this->payerId, $data['paypal']['payerId']);
    }

    public function testFromDataWithArray()
    {
        $arrayData = [
            'paypal' => [
                'clientIpAddress' => $this->clientIp,
                'paypalOrderId' => $this->paypalOrderId,
                'payerId' => $this->payerId,
            ]
        ];

        $payment = PayPalPayment::fromData($arrayData);
        $data = $payment->jsonSerialize();

        $this->assertEquals($this->clientIp, $data['paypal']['clientIpAddress']);
        $this->assertEquals($this->paypalOrderId, $data['paypal']['paypalOrderId']);
        $this->assertEquals($this->payerId, $data['paypal']['payerId']);
    }

    public function testFromDataWithObject()
    {
        $objectData = (object)[
            'paypal' => (object)[
                'clientIpAddress' => $this->clientIp,
                'paypalOrderId' => $this->paypalOrderId,
                'payerId' => $this->payerId,
            ]
        ];

        $payment = PayPalPayment::fromData($objectData);
        $data = $payment->jsonSerialize();

        $this->assertEquals($this->clientIp, $data['paypal']['clientIpAddress']);
        $this->assertEquals($this->paypalOrderId, $data['paypal']['paypalOrderId']);
        $this->assertEquals($this->payerId, $data['paypal']['payerId']);
    }

    public function testFromDataWithoutPayPalWrapper()
    {
        $arrayData = [
            'clientIpAddress' => $this->clientIp,
            'paypalOrderId' => $this->paypalOrderId,
            'payerId' => $this->payerId,
        ];

        $payment = PayPalPayment::fromData($arrayData);
        $data = $payment->jsonSerialize();

        $this->assertEquals($this->clientIp, $data['paypal']['clientIpAddress']);
        $this->assertEquals($this->paypalOrderId, $data['paypal']['paypalOrderId']);
        $this->assertEquals($this->payerId, $data['paypal']['payerId']);
    }

    public function testFromDataWithAlternativeOrderIdField()
    {
        // Test the fallback from 'orderId' to 'paypalOrderId'
        $arrayData = [
            'paypal' => [
                'clientIpAddress' => $this->clientIp,
                'orderId' => $this->paypalOrderId,
                'payerId' => $this->payerId,
            ]
        ];

        $payment = PayPalPayment::fromData($arrayData);
        $data = $payment->jsonSerialize();

        $this->assertEquals($this->clientIp, $data['paypal']['clientIpAddress']);
        $this->assertEquals($this->paypalOrderId, $data['paypal']['paypalOrderId']);
        $this->assertEquals($this->payerId, $data['paypal']['payerId']);
    }

    public function testImplementsPaymentMethodInterface()
    {
        $payment = new PayPalPayment($this->clientIp, $this->paypalOrderId);

        $this->assertInstanceOf(PaymentMethodInterface::class, $payment);
    }

    public function testWithDifferentIpAddress()
    {
        $newIp = '10.0.0.1';
        $payment = new PayPalPayment($newIp, $this->paypalOrderId, $this->payerId);

        $this->assertEquals($newIp, $payment->getClientIpAddress());

        $data = $payment->jsonSerialize();
        $this->assertEquals($newIp, $data['paypal']['clientIpAddress']);
    }

    public function testWithLongOrderId()
    {
        $longOrderId = 'PAYPAL-ORDER-' . str_repeat('1234567890', 10);
        $payment = new PayPalPayment($this->clientIp, $longOrderId);

        $this->assertEquals($longOrderId, $payment->getPaypalOrderId());

        $data = $payment->jsonSerialize();
        $this->assertEquals($longOrderId, $data['paypal']['paypalOrderId']);
    }
}
