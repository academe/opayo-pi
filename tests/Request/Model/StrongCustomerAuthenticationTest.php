<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Model;

use PHPUnit\Framework\TestCase;
use UnexpectedValueException;
use Academe\Opayo\Pi\Request\Enums\BrowserColorDepth;
use Academe\Opayo\Pi\Request\Enums\ChallengeWindowSize;
use Academe\Opayo\Pi\Request\Enums\ThreeDSExemptionIndicator;
use Academe\Opayo\Pi\Request\Enums\TransType;

class StrongCustomerAuthenticationTest extends TestCase
{
    protected function createSca(
        string|ChallengeWindowSize $challengeWindowSize = StrongCustomerAuthentication::CHALLENGE_WINDOW_SIZE_SMALL,
        string|TransType $transType = StrongCustomerAuthentication::TRANS_TYPE_GOODS_AND_SERVICE_PURCHASE
    ): StrongCustomerAuthentication {
        return new StrongCustomerAuthentication(
            'https://example.com/notification',
            '203.0.113.10',
            '*/*',
            true,
            'en-GB',
            'Mozilla/5.0',
            $challengeWindowSize,
            $transType
        );
    }

    public function testLegacyStringsStillAccepted()
    {
        $body = $this->createSca()->jsonSerialize();

        $this->assertSame('Small', $body['challengeWindowSize']);
        $this->assertSame('GoodsAndServicePurchase', $body['transType']);
    }

    public function testConstructorAcceptsEnums()
    {
        $body = $this->createSca(
            ChallengeWindowSize::FullScreen,
            TransType::AccountFunding
        )->jsonSerialize();

        $this->assertSame('FullScreen', $body['challengeWindowSize']);
        $this->assertSame('AccountFunding', $body['transType']);
    }

    public function testBrowserColorDepthAcceptsEnum()
    {
        $body = $this->createSca()
            ->withBrowserColorDepth(BrowserColorDepth::Depth24)
            ->jsonSerialize();

        $this->assertSame(24, $body['browserColorDepth']);
    }

    public function testBrowserColorDepthAcceptsIntConstant()
    {
        // The BROWSER_COLOR_DEPTH_* constants are ints; under strict_types
        // the old string-typed setter rejected the class's own constants.
        $body = $this->createSca()
            ->withBrowserColorDepth(StrongCustomerAuthentication::BROWSER_COLOR_DEPTH_32)
            ->jsonSerialize();

        $this->assertSame(32, $body['browserColorDepth']);
    }

    public function testBrowserColorDepthAcceptsNumericString()
    {
        // The old setter accepted numeric strings, so they must keep working.
        $body = $this->createSca()
            ->withBrowserColorDepth('16')
            ->jsonSerialize();

        $this->assertSame(16, $body['browserColorDepth']);
    }

    public function testThreeDsExemptionIndicatorOmittedByDefault()
    {
        $this->assertArrayNotHasKey(
            'threeDSExemptionIndicator',
            $this->createSca()->jsonSerialize()
        );
    }

    public function testThreeDsExemptionIndicatorAcceptsEnum()
    {
        $body = $this->createSca()
            ->withThreeDsExemptionIndicator(ThreeDSExemptionIndicator::TransactionRiskAnalysis)
            ->jsonSerialize();

        $this->assertSame('TransactionRiskAnalysis', $body['threeDSExemptionIndicator']);
    }

    public function testThreeDsExemptionIndicatorAcceptsString()
    {
        $body = $this->createSca()
            ->withThreeDsExemptionIndicator('LowValue')
            ->jsonSerialize();

        $this->assertSame('LowValue', $body['threeDSExemptionIndicator']);
    }

    public function testThreeDsExemptionIndicatorAsConstructorOption()
    {
        $sca = new StrongCustomerAuthentication(
            'https://example.com/notification',
            '203.0.113.10',
            '*/*',
            true,
            'en-GB',
            'Mozilla/5.0',
            ChallengeWindowSize::Small,
            TransType::GoodsAndServicePurchase,
            ['threeDsExemptionIndicator' => ThreeDSExemptionIndicator::LowValue]
        );

        $this->assertSame('LowValue', $sca->jsonSerialize()['threeDSExemptionIndicator']);
    }

    public function testInvalidBrowserColorDepthRejected()
    {
        $this->expectException(UnexpectedValueException::class);

        $this->createSca()->withBrowserColorDepth(13);
    }
}
