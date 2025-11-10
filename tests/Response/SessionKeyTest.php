<?php

namespace Academe\Opayo\Pi\Response;

use PHPUnit\Framework\TestCase;
use DateTime;

class SessionKeyTest extends TestCase
{
    public function testFromDataWithValidSessionKey()
    {
        $data = [
            'merchantSessionKey' => '{11111111-1111-1111-1111-111111111111}',
            'expiry' => '2025-12-31T23:59:59.000+00:00',
        ];

        $sessionKey = SessionKey::fromData($data);

        $this->assertEquals('{11111111-1111-1111-1111-111111111111}', $sessionKey->getMerchantSessionKey());
        $this->assertInstanceOf(DateTime::class, $sessionKey->getExpiry());
    }

    public function testToStringReturnsSessionKey()
    {
        $data = [
            'merchantSessionKey' => '{11111111-1111-1111-1111-111111111111}',
            'expiry' => '2025-12-31T23:59:59.000+00:00',
        ];

        $sessionKey = SessionKey::fromData($data);

        $this->assertEquals('{11111111-1111-1111-1111-111111111111}', (string)$sessionKey);
    }

    public function testIsExpiredWithFutureDate()
    {
        $futureDate = (new DateTime())->modify('+1 hour')->format('Y-m-d\TH:i:s.000P');

        $data = [
            'merchantSessionKey' => '{11111111-1111-1111-1111-111111111111}',
            'expiry' => $futureDate,
        ];

        $sessionKey = SessionKey::fromData($data);

        $this->assertFalse($sessionKey->isExpired());
    }

    public function testIsExpiredWithPastDate()
    {
        $pastDate = (new DateTime())->modify('-1 hour')->format('Y-m-d\TH:i:s.000P');

        $data = [
            'merchantSessionKey' => '{11111111-1111-1111-1111-111111111111}',
            'expiry' => $pastDate,
        ];

        $sessionKey = SessionKey::fromData($data);

        $this->assertTrue($sessionKey->isExpired());
    }

    public function testIsExpiredWithNullExpiry()
    {
        $data = [
            'merchantSessionKey' => '{11111111-1111-1111-1111-111111111111}',
        ];

        $sessionKey = SessionKey::fromData($data);

        $this->assertTrue($sessionKey->isExpired());
    }

    public function testIsValidWithValidKey()
    {
        $futureDate = (new DateTime())->modify('+1 hour')->format('Y-m-d\TH:i:s.000P');

        $data = [
            'merchantSessionKey' => '{11111111-1111-1111-1111-111111111111}',
            'expiry' => $futureDate,
        ];

        $sessionKey = SessionKey::fromData($data);

        $this->assertTrue($sessionKey->isValid());
    }

    public function testIsValidWithExpiredKey()
    {
        $pastDate = (new DateTime())->modify('-1 hour')->format('Y-m-d\TH:i:s.000P');

        $data = [
            'merchantSessionKey' => '{11111111-1111-1111-1111-111111111111}',
            'expiry' => $pastDate,
        ];

        $sessionKey = SessionKey::fromData($data);

        $this->assertFalse($sessionKey->isValid());
    }

    public function testIsValidWithNullKey()
    {
        $data = [
            'expiry' => '2025-12-31T23:59:59.000+00:00',
        ];

        $sessionKey = SessionKey::fromData($data);

        $this->assertFalse($sessionKey->isValid());
    }

    public function testJsonSerialize()
    {
        $data = [
            'merchantSessionKey' => '{11111111-1111-1111-1111-111111111111}',
            'expiry' => '2025-12-31T23:59:59.000+00:00',
        ];

        $sessionKey = SessionKey::fromData($data);
        $serialized = $sessionKey->jsonSerialize();

        $this->assertArrayHasKey('merchantSessionKey', $serialized);
        $this->assertArrayHasKey('expiry', $serialized);
        $this->assertEquals('{11111111-1111-1111-1111-111111111111}', $serialized['merchantSessionKey']);
    }

    public function testToHtmlElements()
    {
        $data = [
            'merchantSessionKey' => '{11111111-1111-1111-1111-111111111111}',
            'expiry' => '2025-12-31T23:59:59.000+00:00',
        ];

        $sessionKey = SessionKey::fromData($data);
        $htmlElements = $sessionKey->toHtmlElements();

        $this->assertArrayHasKey('merchantSessionKey', $htmlElements);
        $this->assertEquals('input', $htmlElements['merchantSessionKey']['name']);
        $this->assertEquals('hidden', $htmlElements['merchantSessionKey']['attributes']['type']);
        $this->assertEquals('{11111111-1111-1111-1111-111111111111}', $htmlElements['merchantSessionKey']['attributes']['value']);
    }
}
