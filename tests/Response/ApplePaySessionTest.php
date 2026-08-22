<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response;

use Academe\Opayo\Pi\Factory\ResponseFactory;
use PHPUnit\Framework\TestCase;

class ApplePaySessionTest extends TestCase
{
    /**
     * Shape documented for POST /applepay/sessions (Opayo-managed certificate).
     */
    protected function data(): array
    {
        return [
            'status' => 'Ok',
            'statusDetail' => 'Session created successfully.',
            'epochTimestamp' => '1570100718688',
            'expiresAt' => '1570104318688',
            'merchantSessionIdentifier' => 'SSH0123456789',
            'nonce' => 'abc123',
            'merchantIdentifier' => 'MERCHANT-ID-HASH',
            'domainName' => 'www.example.com',
            'displayName' => 'Pay Test',
            'sessionValidationToken' => 'VALIDATION-TOKEN',
            'signature' => 'SIGNATURE-BYTES',
        ];
    }

    public function testParsesAllFields()
    {
        $session = ApplePaySession::fromData($this->data(), 201);

        $this->assertTrue($session->isSuccess());
        $this->assertSame('Ok', $session->getStatus());
        $this->assertSame(201, $session->getHttpCode());
        $this->assertSame('VALIDATION-TOKEN', $session->getSessionValidationToken());
        $this->assertSame('SSH0123456789', $session->getMerchantSessionIdentifier());
        $this->assertSame('abc123', $session->getNonce());
        $this->assertSame('MERCHANT-ID-HASH', $session->getMerchantIdentifier());
        $this->assertSame('www.example.com', $session->getDomainName());
        $this->assertSame('Pay Test', $session->getDisplayName());
        $this->assertSame('SIGNATURE-BYTES', $session->getSignature());
        $this->assertSame('1570100718688', $session->getEpochTimestamp());
        $this->assertSame('1570104318688', $session->getExpiresAt());
    }

    public function testMerchantSessionForBrowserOmitsStatusAndToken()
    {
        $session = ApplePaySession::fromData($this->data());

        $this->assertSame([
            'epochTimestamp' => '1570100718688',
            'expiresAt' => '1570104318688',
            'merchantSessionIdentifier' => 'SSH0123456789',
            'nonce' => 'abc123',
            'merchantIdentifier' => 'MERCHANT-ID-HASH',
            'domainName' => 'www.example.com',
            'displayName' => 'Pay Test',
            'signature' => 'SIGNATURE-BYTES',
        ], $session->getMerchantSession());
    }

    public function testAcceptsApiReferenceSpellingOfEpochTimeStamp()
    {
        $data = $this->data();
        $data['epochTimeStamp'] = $data['epochTimestamp'];
        unset($data['epochTimestamp']);

        $session = ApplePaySession::fromData($data);

        $this->assertSame('1570100718688', $session->getEpochTimestamp());
        $this->assertSame('1570100718688', $session->getMerchantSession()['epochTimestamp']);
    }

    public function testFailureStatus()
    {
        $session = ApplePaySession::fromData([
            'status' => 'Invalid',
            'statusCode' => '6118',
            'statusDetail' => 'Domain not registered.',
        ], 422);

        $this->assertFalse($session->isSuccess());
        $this->assertSame('6118', $session->getStatusCode());
        $this->assertSame('Domain not registered.', $session->getStatusDetail());
        $this->assertNull($session->getSessionValidationToken());
        $this->assertSame([], $session->getMerchantSession());
    }

    public function testFactoryRecognisesIt()
    {
        $this->assertInstanceOf(ApplePaySession::class, ResponseFactory::fromData($this->data(), 201));
    }

    public function testJsonRoundTrip()
    {
        $session = ApplePaySession::fromData($this->data());

        $this->assertEquals($session, ApplePaySession::fromData(json_encode($session)));
    }
}
