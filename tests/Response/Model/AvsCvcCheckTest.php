<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response\Model;

use PHPUnit\Framework\TestCase;
use Academe\Opayo\Pi\Response\Enums\AvsCvcCheckResult;
use Academe\Opayo\Pi\Response\Enums\AvsCvcCheckStatus;

class AvsCvcCheckTest extends TestCase
{
    public function testEnumAccessors()
    {
        $check = AvsCvcCheck::fromData([
            'status' => 'AddressMatchOnly',
            'address' => 'Matched',
            'postalCode' => 'NotMatched',
            'securityCode' => 'NotProvided',
        ]);

        $this->assertSame(AvsCvcCheckStatus::ADDRESS_MATCH_ONLY, $check->getStatusEnum());
        $this->assertSame(AvsCvcCheckResult::MATCHED, $check->getAddressEnum());
        $this->assertSame(AvsCvcCheckResult::NOT_MATCHED, $check->getPostalCodeEnum());
        $this->assertSame(AvsCvcCheckResult::NOT_PROVIDED, $check->getSecurityCodeEnum());
    }

    public function testEnumAccessorsNullWhenNotSet()
    {
        $check = new AvsCvcCheck();

        $this->assertNull($check->getStatusEnum());
        $this->assertNull($check->getAddressEnum());
        $this->assertNull($check->getPostalCodeEnum());
        $this->assertNull($check->getSecurityCodeEnum());
    }

    public function testUnknownValuesPreservedAsStringButNullAsEnum()
    {
        $check = AvsCvcCheck::fromData([
            'status' => 'SomeFutureStatus',
            'address' => 'SomeFutureResult',
        ]);

        $this->assertNull($check->getStatusEnum());
        $this->assertNull($check->getAddressEnum());
        $this->assertSame('SomeFutureStatus', $check->getStatus());
        $this->assertSame('SomeFutureResult', $check->getAddress());
    }
}
