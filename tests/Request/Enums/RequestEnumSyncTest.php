<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Enums;

use PHPUnit\Framework\TestCase;
use Academe\Opayo\Pi\Request\CreatePayment;
use Academe\Opayo\Pi\Request\Model\CredentialType;
use Academe\Opayo\Pi\Request\Model\StrongCustomerAuthentication as Sca;

/**
 * The enums and the legacy class constants duplicate the gateway values
 * while the package supports PHP 8.1 (constants cannot be derived from
 * enum cases until PHP 8.2). These tests fail if the two drift apart.
 */
class RequestEnumSyncTest extends TestCase
{
    public function testEntryMethodMatchesConstants()
    {
        $this->assertSame(
            array_values(CreatePayment::getEntryMethods()),
            array_map(fn ($case) => $case->value, EntryMethod::cases())
        );
    }

    public function testApplyAvsCvcCheckMatchesConstants()
    {
        $this->assertSame(
            array_values(CreatePayment::getApplyAvsCvcChecks()),
            array_map(fn ($case) => $case->value, ApplyAvsCvcCheck::cases())
        );
    }

    public function testCofUsageMatchesConstants()
    {
        $this->assertSame(
            [
                CredentialType::COF_USAGE_FIRST,
                CredentialType::COF_USAGE_SUBSEQUENT,
            ],
            array_map(fn ($case) => $case->value, CofUsage::cases())
        );
    }

    public function testInitiatedTypeMatchesConstants()
    {
        $this->assertSame(
            [
                CredentialType::INITIATED_TYPE_CONSUMER_INITIATED,
                CredentialType::INITIATED_TYPE_MERCHANT_INITIATED,
            ],
            array_map(fn ($case) => $case->value, InitiatedType::cases())
        );
    }

    public function testMitTypeMatchesConstants()
    {
        $this->assertSame(
            [
                CredentialType::MIT_TYPE_RECURRING,
                CredentialType::MIT_TYPE_INSTALMENT,
                CredentialType::MIT_TYPE_UNSCHEDULED,
                CredentialType::MIT_TYPE_INCREMENTAL,
                CredentialType::MIT_TYPE_DELAYEDCHARGE,
                CredentialType::MIT_TYPE_NOSHOW,
                CredentialType::MIT_TYPE_REAUTHORISATION,
                CredentialType::MIT_TYPE_RESUBMISSION,
            ],
            array_map(fn ($case) => $case->value, MitType::cases())
        );
    }

    public function testChallengeWindowSizeMatchesConstants()
    {
        $this->assertSame(
            [
                Sca::CHALLENGE_WINDOW_SIZE_SMALL,
                Sca::CHALLENGE_WINDOW_SIZE_MEDIUM,
                Sca::CHALLENGE_WINDOW_SIZE_LARGE,
                Sca::CHALLENGE_WINDOW_SIZE_EXTRALARGE,
                Sca::CHALLENGE_WINDOW_SIZE_FULLSCREEN,
            ],
            array_map(fn ($case) => $case->value, ChallengeWindowSize::cases())
        );
    }

    public function testTransTypeMatchesConstants()
    {
        $this->assertSame(
            [
                Sca::TRANS_TYPE_GOODS_AND_SERVICE_PURCHASE,
                Sca::TRANS_TYPE_CHECK_ACCEPTANCE,
                Sca::TRANS_TYPE_ACCOUNT_FUNDING,
                Sca::TRANS_TYPE_QUASI_CASH_TRANSACTION,
                Sca::TRANS_TYPE_PREPAID_ACTIVATION_AND_LOAD,
            ],
            array_map(fn ($case) => $case->value, TransType::cases())
        );
    }

    public function testBrowserColorDepthMatchesConstants()
    {
        $this->assertSame(
            [
                Sca::BROWSER_COLOR_DEPTH_1,
                Sca::BROWSER_COLOR_DEPTH_4,
                Sca::BROWSER_COLOR_DEPTH_8,
                Sca::BROWSER_COLOR_DEPTH_15,
                Sca::BROWSER_COLOR_DEPTH_16,
                Sca::BROWSER_COLOR_DEPTH_24,
                Sca::BROWSER_COLOR_DEPTH_32,
                Sca::BROWSER_COLOR_DEPTH_48,
            ],
            array_map(fn ($case) => $case->value, BrowserColorDepth::cases())
        );
    }

    public function testApply3DSecureMatchesConstants()
    {
        // The deprecated ForceIgnoringRules constant (removed from the API
        // spec 2023-10-26) is deliberately not carried into the enum, so the
        // enum is a subset of the constants.
        $this->assertSame(
            ['UseMSPSetting', 'Force', 'Disable'],
            array_map(fn ($case) => $case->value, Apply3DSecure::cases())
        );

        foreach (Apply3DSecure::cases() as $case) {
            $this->assertContains($case->value, CreatePayment::getApply3DSecures());
        }
    }
}
