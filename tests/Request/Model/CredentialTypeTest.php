<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Model;

use PHPUnit\Framework\TestCase;

class CredentialTypeTest extends TestCase
{
    public function testMitTypeOmittedWhenNull()
    {
        // A first CIT use of a card has no mitType, so the key must not
        // be sent to the gateway at all.
        $credentialType = CredentialType::createForNewReusableCard();

        $body = $credentialType->jsonSerialize();

        $this->assertSame('First', $body['cofUsage']);
        $this->assertSame('CIT', $body['initiatedType']);
        $this->assertArrayNotHasKey('mitType', $body);
    }

    public function testCreateForRepeatPaymentDefaultsToUnscheduled()
    {
        $body = CredentialType::createForRepeatPayment()->jsonSerialize();

        $this->assertSame(
            [
                'cofUsage' => 'Subsequent',
                'initiatedType' => 'MIT',
                'mitType' => 'Unscheduled',
            ],
            $body
        );
    }

    public function testCreateForRepeatPaymentRecurring()
    {
        $body = CredentialType::createForRepeatPayment(
            CredentialType::MIT_TYPE_RECURRING,
            '20270301',
            28
        )->jsonSerialize();

        $this->assertSame(
            [
                'cofUsage' => 'Subsequent',
                'initiatedType' => 'MIT',
                'mitType' => 'Recurring',
                'recurringExpiry' => '20270301',
                'recurringFrequency' => 28,
            ],
            $body
        );
    }

    public function testAllFieldsSerialized()
    {
        $credentialType = new CredentialType(
            CredentialType::COF_USAGE_SUBSEQUENT,
            CredentialType::INITIATED_TYPE_MERCHANT_INITIATED,
            CredentialType::MIT_TYPE_RECURRING,
            '20270301',
            28,
            6
        );

        $this->assertSame(
            [
                'cofUsage' => 'Subsequent',
                'initiatedType' => 'MIT',
                'mitType' => 'Recurring',
                'recurringExpiry' => '20270301',
                'recurringFrequency' => 28,
                'purchaseInstalData' => 6,
            ],
            $credentialType->jsonSerialize()
        );
    }
}
