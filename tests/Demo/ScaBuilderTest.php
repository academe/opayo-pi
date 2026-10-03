<?php

namespace Academe\Opayo\Pi\Demo;

use Academe\Opayo\Pi\Request\Model\StrongCustomerAuthentication;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../demo/shared.php';

/**
 * scaFromRequest() centralises the strongCustomerAuthentication object the
 * demo sends on every CreatePayment (card and wallet alike): a Google
 * PAN_ONLY token may still be challenged, so it is always present.
 */
class ScaBuilderTest extends TestCase
{
    public function testBuildsScaFromPostedBrowserFields()
    {
        $post = [
            'browserColorDepth' => '24',
            'browserScreenHeight' => '1080',
            'browserScreenWidth' => '1920',
            'browserTz' => '0',
            'browserLanguage' => 'en-GB',
        ];

        $sca = scaFromRequest($post, 'http://127.0.0.1:8000/notification.php', '10.0.0.1');
        $data = $sca->jsonSerialize();

        $this->assertInstanceOf(StrongCustomerAuthentication::class, $sca);
        $this->assertSame('http://127.0.0.1:8000/notification.php', $data['notificationURL']);
        $this->assertSame('10.0.0.1', $data['browserIP']);
        $this->assertSame(1080, $data['browserScreenHeight']);
        $this->assertSame(1920, $data['browserScreenWidth']);
    }

    public function testForcesIpv4LoopbackForIpv6ClientIp()
    {
        $sca = scaFromRequest([], 'http://x/n.php', '::1');

        $this->assertSame('127.0.0.1', $sca->jsonSerialize()['browserIP']);
    }

    public function testDefaultsLanguageWhenBlank()
    {
        $sca = scaFromRequest(['browserLanguage' => ''], 'http://x/n.php', '10.0.0.1');

        $this->assertSame('en-GB', $sca->jsonSerialize()['browserLanguage']);
    }
}
