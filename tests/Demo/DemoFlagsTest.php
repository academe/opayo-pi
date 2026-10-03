<?php

namespace Academe\Opayo\Pi\Demo;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../demo/shared.php';

/**
 * The DEMO_ENABLE_* flags that decide which demo panels are offered, and the
 * Apple Pay domain resolver. These are demo-side config helpers in
 * demo/shared.php, exercised here in isolation.
 */
class DemoFlagsTest extends TestCase
{
    protected function setUp(): void
    {
        foreach ([
            'DEMO_ENABLE_CARD',
            'DEMO_ENABLE_GOOGLE_PAY',
            'DEMO_ENABLE_APPLE_PAY',
            'DEMO_ENABLE_PAY_PAL',
            'OPAYO_APPLE_PAY_DOMAIN',
        ] as $k) {
            unset($_ENV[$k]);
        }
    }

    public function testDefaultsToEnabledWhenUnset()
    {
        $this->assertTrue(demoMethodEnabled('card'));
        $this->assertTrue(demoMethodEnabled('googlePay'));
        $this->assertTrue(demoMethodEnabled('applePay'));
        $this->assertTrue(demoMethodEnabled('payPal'));
    }

    public function testZeroDisables()
    {
        $_ENV['DEMO_ENABLE_GOOGLE_PAY'] = '0';

        $this->assertFalse(demoMethodEnabled('googlePay'));
        $this->assertTrue(demoMethodEnabled('card'));
    }

    public function testApplePayDomainFallsBackToHost()
    {
        $_ENV['OPAYO_APPLE_PAY_DOMAIN'] = 'shop.example.com';

        $this->assertSame('shop.example.com', applePayDomain());
    }
}
