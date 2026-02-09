<?php

namespace Academe\Opayo\Pi\Request\Model;

use PHPUnit\Framework\TestCase;

class GooglePayPaymentTest extends TestCase
{
    protected string $clientIp = '192.168.1.100';
    protected string $payload = 'base64EncodedGooglePayTokenHere==';

    public function testConstruct()
    {
        $payment = new GooglePayPayment($this->clientIp, $this->payload);

        $data = $payment->jsonSerialize();

        $this->assertArrayHasKey('googlePay', $data);
        $this->assertEquals($this->clientIp, $data['googlePay']['clientIpAddress']);
        $this->assertEquals($this->payload, $data['googlePay']['payload']);
    }

    public function testGetters()
    {
        $payment = new GooglePayPayment($this->clientIp, $this->payload);

        $this->assertEquals($this->clientIp, $payment->getClientIpAddress());
        $this->assertEquals($this->payload, $payment->getPayload());
    }

    public function testJsonSerialize()
    {
        $payment = new GooglePayPayment($this->clientIp, $this->payload);

        $data = $payment->jsonSerialize();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('googlePay', $data);
        $this->assertIsArray($data['googlePay']);
        $this->assertCount(2, $data['googlePay']);
    }

    public function testFromDataWithJsonString()
    {
        $json = json_encode([
            'googlePay' => [
                'clientIpAddress' => $this->clientIp,
                'payload' => $this->payload,
            ]
        ]);

        $payment = GooglePayPayment::fromData($json);
        $data = $payment->jsonSerialize();

        $this->assertEquals($this->clientIp, $data['googlePay']['clientIpAddress']);
        $this->assertEquals($this->payload, $data['googlePay']['payload']);
    }

    public function testFromDataWithArray()
    {
        $arrayData = [
            'googlePay' => [
                'clientIpAddress' => $this->clientIp,
                'payload' => $this->payload,
            ]
        ];

        $payment = GooglePayPayment::fromData($arrayData);
        $data = $payment->jsonSerialize();

        $this->assertEquals($this->clientIp, $data['googlePay']['clientIpAddress']);
        $this->assertEquals($this->payload, $data['googlePay']['payload']);
    }

    public function testFromDataWithObject()
    {
        $objectData = (object)[
            'googlePay' => (object)[
                'clientIpAddress' => $this->clientIp,
                'payload' => $this->payload,
            ]
        ];

        $payment = GooglePayPayment::fromData($objectData);
        $data = $payment->jsonSerialize();

        $this->assertEquals($this->clientIp, $data['googlePay']['clientIpAddress']);
        $this->assertEquals($this->payload, $data['googlePay']['payload']);
    }

    public function testFromDataWithoutGooglePayWrapper()
    {
        $arrayData = [
            'clientIpAddress' => $this->clientIp,
            'payload' => $this->payload,
        ];

        $payment = GooglePayPayment::fromData($arrayData);
        $data = $payment->jsonSerialize();

        $this->assertEquals($this->clientIp, $data['googlePay']['clientIpAddress']);
        $this->assertEquals($this->payload, $data['googlePay']['payload']);
    }

    public function testImplementsPaymentMethodInterface()
    {
        $payment = new GooglePayPayment($this->clientIp, $this->payload);

        $this->assertInstanceOf(PaymentMethodInterface::class, $payment);
    }

    public function testWithDifferentIpAddress()
    {
        $newIp = '10.0.0.1';
        $payment = new GooglePayPayment($newIp, $this->payload);

        $this->assertEquals($newIp, $payment->getClientIpAddress());

        $data = $payment->jsonSerialize();
        $this->assertEquals($newIp, $data['googlePay']['clientIpAddress']);
    }

    public function testWithLongPayload()
    {
        $longPayload = base64_encode(str_repeat('GooglePayTokenData', 100));
        $payment = new GooglePayPayment($this->clientIp, $longPayload);

        $this->assertEquals($longPayload, $payment->getPayload());

        $data = $payment->jsonSerialize();
        $this->assertEquals($longPayload, $data['googlePay']['payload']);
    }
}
