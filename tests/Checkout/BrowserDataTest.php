<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Checkout;

use Academe\Opayo\Pi\Request\Enums\ChallengeWindowSize;
use Academe\Opayo\Pi\Request\Enums\TransType;
use Academe\Opayo\Pi\Request\Model\StrongCustomerAuthentication;
use PHPUnit\Framework\TestCase;

class BrowserDataTest extends TestCase
{
    public function testReadsPostedFields()
    {
        $data = BrowserData::fromArray([
            'browserLanguage' => 'fr-FR',
            'browserColorDepth' => '32',
            'browserScreenHeight' => '1080',
            'browserScreenWidth' => '1920',
            'browserTz' => '-60',
        ]);

        $this->assertSame('fr-FR', $data->language);
        $this->assertSame(32, $data->colorDepth);
        $this->assertSame(1080, $data->screenHeight);
        $this->assertSame(1920, $data->screenWidth);
        $this->assertSame(-60, $data->timezoneOffset);
    }

    public function testDefaultsWhenFieldsMissing()
    {
        $data = BrowserData::fromArray([]);

        $this->assertSame('en-GB', $data->language);
        $this->assertSame(24, $data->colorDepth);
        $this->assertSame(0, $data->screenHeight);
        $this->assertSame(0, $data->screenWidth);
        $this->assertSame(0, $data->timezoneOffset);
    }

    public function testJunkFallsBackToDefaults()
    {
        $data = BrowserData::fromArray([
            'browserLanguage' => '   ',
            'browserColorDepth' => '30',
            'browserScreenHeight' => 'tall',
            'browserTz' => 'abc',
        ]);

        $this->assertSame('en-GB', $data->language);
        $this->assertSame(24, $data->colorDepth);
        $this->assertSame(0, $data->screenHeight);
        $this->assertSame(0, $data->timezoneOffset);
    }

    public function testFieldsConstantListsWhatIsRead()
    {
        $this->assertSame(
            ['browserLanguage', 'browserColorDepth', 'browserScreenHeight', 'browserScreenWidth', 'browserTz'],
            BrowserData::FIELDS
        );
    }

    public function testBuildsScaIdenticalToOneBuiltByHand()
    {
        $sca = BrowserData::fromArray([
            'browserLanguage' => 'en-GB',
            'browserColorDepth' => '24',
            'browserScreenHeight' => '1080',
            'browserScreenWidth' => '1920',
            'browserTz' => '0',
        ])->toStrongCustomerAuthentication(
            'https://shop.example/notification.php',
            '10.0.0.1',
            'text/html',
            'Mozilla/5.0'
        );

        $byHand = new StrongCustomerAuthentication(
            'https://shop.example/notification.php',
            '10.0.0.1',
            'text/html',
            true,
            'en-GB',
            'Mozilla/5.0',
            ChallengeWindowSize::Medium,
            TransType::GoodsAndServicePurchase,
            [
                'browserJavaEnabled' => false,
                'browserColorDepth' => 24,
                'browserScreenHeight' => 1080,
                'browserScreenWidth' => 1920,
                'browserTz' => 0,
            ]
        );

        $this->assertSame(json_encode($byHand), json_encode($sca));
    }

    public function testIpv6ClientIpBecomesIpv4Loopback()
    {
        $sca = BrowserData::fromArray([])->toStrongCustomerAuthentication('https://x.example/n', '::1', 'text/html', 'UA');

        $this->assertSame('127.0.0.1', $sca->jsonSerialize()['browserIP']);
    }

    public function testIpv4ClientIpIsKept()
    {
        $sca = BrowserData::fromArray([])->toStrongCustomerAuthentication('https://x.example/n', '203.0.113.9', 'text/html', 'UA');

        $this->assertSame('203.0.113.9', $sca->jsonSerialize()['browserIP']);
    }

    public function testBlankHeadersGetSafeValues()
    {
        $data = BrowserData::fromArray([])->toStrongCustomerAuthentication('https://x.example/n', '10.0.0.1', '', '')->jsonSerialize();

        $this->assertSame('*/*', $data['browserAcceptHeader']);
        $this->assertSame('Unknown', $data['browserUserAgent']);
    }
}
