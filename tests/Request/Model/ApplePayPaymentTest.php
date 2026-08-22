<?php

namespace Academe\Opayo\Pi\Request\Model;

use PHPUnit\Framework\TestCase;

/**
 * paymentMethod.applePay wire format, per the Opayo Pi API reference:
 * merchantSessionKey, clientIpAddress, paymentData (base64, including the
 * top-level paymentData node), optional sessionValidationToken /
 * applicationData / displayName / paymentMethodType.
 */
class ApplePayPaymentTest extends TestCase
{
    protected string $msk = 'MSK-0123456789';
    protected string $clientIp = '192.168.1.100';
    protected string $paymentData = 'eyJwYXltZW50RGF0YSI6e319';
    protected string $sessionValidationToken = 'sessionToken123';

    public function testMinimalMerchantManagedCertificate()
    {
        $payment = new ApplePayPayment($this->msk, $this->clientIp, $this->paymentData);

        $this->assertSame([
            'applePay' => [
                'merchantSessionKey' => $this->msk,
                'clientIpAddress' => $this->clientIp,
                'paymentData' => $this->paymentData,
            ],
        ], $payment->jsonSerialize());
    }

    public function testOpayoManagedCertificateIncludesSessionValidationToken()
    {
        $payment = new ApplePayPayment($this->msk, $this->clientIp, $this->paymentData, $this->sessionValidationToken);

        $data = $payment->jsonSerialize()['applePay'];

        $this->assertSame($this->sessionValidationToken, $data['sessionValidationToken']);
        $this->assertArrayNotHasKey('applicationData', $data);
        $this->assertArrayNotHasKey('displayName', $data);
        $this->assertArrayNotHasKey('paymentMethodType', $data);
    }

    public function testOptionalFields()
    {
        $payment = new ApplePayPayment(
            $this->msk,
            $this->clientIp,
            $this->paymentData,
            null,
            'appData==',
            'Visa 1234',
            'debit'
        );

        $data = $payment->jsonSerialize()['applePay'];

        $this->assertArrayNotHasKey('sessionValidationToken', $data);
        $this->assertSame('appData==', $data['applicationData']);
        $this->assertSame('Visa 1234', $data['displayName']);
        $this->assertSame('debit', $data['paymentMethodType']);
    }

    public function testNeverEmitsLegacyPayloadKey()
    {
        $payment = new ApplePayPayment($this->msk, $this->clientIp, $this->paymentData);

        $this->assertArrayNotHasKey('payload', $payment->jsonSerialize()['applePay']);
    }

    public function testGetters()
    {
        $payment = new ApplePayPayment($this->msk, $this->clientIp, $this->paymentData, $this->sessionValidationToken);

        $this->assertSame($this->msk, $payment->getMerchantSessionKey());
        $this->assertSame($this->clientIp, $payment->getClientIpAddress());
        $this->assertSame($this->paymentData, $payment->getPaymentData());
        $this->assertSame($this->sessionValidationToken, $payment->getSessionValidationToken());
        $this->assertNull($payment->getApplicationData());
    }

    public function testWithSessionValidationTokenIsImmutable()
    {
        $payment = new ApplePayPayment($this->msk, $this->clientIp, $this->paymentData);
        $withToken = $payment->withSessionValidationToken($this->sessionValidationToken);

        $this->assertNotSame($payment, $withToken);
        $this->assertNull($payment->getSessionValidationToken());
        $this->assertSame($this->sessionValidationToken, $withToken->getSessionValidationToken());
    }

    public function testFromAppleTokenEncodesPaymentDataAndPicksOptionalFields()
    {
        // The shape Apple hands to session.onpaymentauthorized (event.payment.token).
        $token = [
            'paymentData' => [
                'version' => 'EC_v1',
                'data' => '3+f4oOTwPa6f1UZ6tG',
                'signature' => 'MIAGCSqGSIb3DQ',
                'header' => [
                    'applicationData' => 'FOeVKLA',
                    'ephemeralPublicKey' => 'MFkwEwYHK',
                    'publicKeyHash' => 'l0CnXdMv',
                    'transactionId' => '32b4f3',
                ],
            ],
            'paymentMethod' => ['displayName' => 'Visa 1234', 'network' => 'Visa', 'type' => 'debit'],
            'transactionIdentifier' => '32b4f3',
        ];

        $payment = ApplePayPayment::fromAppleToken($this->msk, $this->clientIp, $token, $this->sessionValidationToken);
        $data = $payment->jsonSerialize()['applePay'];

        // paymentData is base64 of {"paymentData": {...}} - the top-level node is included.
        $decoded = json_decode(base64_decode($data['paymentData']), true);
        $this->assertSame(['paymentData'], array_keys($decoded));
        $this->assertSame('EC_v1', $decoded['paymentData']['version']);

        $this->assertSame('FOeVKLA', $data['applicationData']);
        $this->assertSame('Visa 1234', $data['displayName']);
        $this->assertSame('debit', $data['paymentMethodType']);
        $this->assertSame($this->sessionValidationToken, $data['sessionValidationToken']);
    }

    public function testFromAppleTokenAcceptsWholePaymentObjectOrJson()
    {
        $payment = ['token' => [
            'paymentData' => ['version' => 'EC_v1'],
            'paymentMethod' => ['displayName' => 'MC 9999'],
        ]];

        $fromArray = ApplePayPayment::fromAppleToken($this->msk, $this->clientIp, $payment);
        $fromJson = ApplePayPayment::fromAppleToken($this->msk, $this->clientIp, json_encode($payment));

        $this->assertSame('MC 9999', $fromArray->getDisplayName());
        $this->assertSame($fromArray->jsonSerialize(), $fromJson->jsonSerialize());
    }

    public function testFromDataRoundTrip()
    {
        $original = new ApplePayPayment(
            $this->msk,
            $this->clientIp,
            $this->paymentData,
            $this->sessionValidationToken,
            'a',
            'Visa 1',
            'credit'
        );

        $this->assertSame(
            $original->jsonSerialize(),
            ApplePayPayment::fromData(json_encode($original->jsonSerialize()))->jsonSerialize()
        );
    }

    public function testFromDataWithoutWrapperAndLegacyPayloadKey()
    {
        $payment = ApplePayPayment::fromData([
            'merchantSessionKey' => $this->msk,
            'clientIpAddress' => $this->clientIp,
            'payload' => $this->paymentData, // name used by earlier releases
        ]);

        $this->assertSame($this->paymentData, $payment->getPaymentData());
        $this->assertSame($this->paymentData, $payment->jsonSerialize()['applePay']['paymentData']);
    }

    public function testImplementsPaymentMethodInterface()
    {
        $this->assertInstanceOf(
            PaymentMethodInterface::class,
            new ApplePayPayment($this->msk, $this->clientIp, $this->paymentData)
        );
    }
}
