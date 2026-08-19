<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response;

use PHPUnit\Framework\TestCase;
use Academe\Opayo\Pi\Response\Enums\Secure3DStatus;

class Secure3DTest extends TestCase
{
    public function testGetStatusEnumReturnsEnum()
    {
        $response = new Secure3D(['status' => 'Authenticated'], 200);

        $this->assertSame(Secure3DStatus::AUTHENTICATED, $response->getStatusEnum());
        $this->assertSame('Authenticated', $response->getStatus());
        $this->assertTrue($response->isSuccess());
    }

    public function testGetStatusEnumIsCaseInsensitive()
    {
        $response = new Secure3D(['status' => 'cardnotenrolled'], 200);

        $this->assertSame(Secure3DStatus::CARD_NOT_ENROLLED, $response->getStatusEnum());
    }

    public function testGetStatusEnumNullWhenNotSet()
    {
        $response = new Secure3D([], 200);

        $this->assertNull($response->getStatusEnum());
    }

    public function testUnknownStatusPreservedAsStringButNullAsEnum()
    {
        // The gateway may add statuses; the raw string must survive even
        // when the enum cannot represent it.
        $response = new Secure3D(['status' => 'SomeFutureStatus'], 200);

        $this->assertNull($response->getStatusEnum());
        $this->assertSame('SomeFutureStatus', $response->getStatus());
    }

    public function testEnumIsSuccessHelper()
    {
        $this->assertTrue(Secure3DStatus::AUTHENTICATED->isSuccess());
        $this->assertFalse(Secure3DStatus::NOT_AUTHENTICATED->isSuccess());
        $this->assertFalse(Secure3DStatus::INCOMPLETE->isSuccess());
    }
}
