<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Demo;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../demo/config.php';
require_once __DIR__ . '/../../demo/layout.php';

/**
 * The demo's pure helpers in demo/config.php: method switches, URLs and the
 * one-credential rule for payment posts.
 */
class ConfigTest extends TestCase
{
    public function testAllMethodsEnabledByDefault()
    {
        $this->assertSame(['card', 'googlepay', 'applepay', 'paypal'], \enabledMethods([]));
    }

    public function testZeroSwitchesAMethodOff()
    {
        $this->assertSame(
            ['card', 'applepay', 'paypal'],
            \enabledMethods(['DEMO_ENABLE_GOOGLE_PAY' => '0'])
        );
    }

    public function testParsesEnvFile()
    {
        $file = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($file, "# comment\nOPAYO_VENDOR_NAME = shop \n\nBROKEN LINE\nKEY=a=b\n");

        $this->assertSame(['OPAYO_VENDOR_NAME' => 'shop', 'KEY' => 'a=b'], \parseEnvFile($file));

        unlink($file);
    }

    public function testBaseUrlPlainHttp()
    {
        $this->assertSame('http://127.0.0.1:8000', \baseUrl(['HTTP_HOST' => '127.0.0.1:8000']));
    }

    public function testBaseUrlHttps()
    {
        $this->assertSame('https://shop.example', \baseUrl(['HTTP_HOST' => 'shop.example', 'HTTPS' => 'on']));
    }

    public function testBaseUrlBehindTunnel()
    {
        $this->assertSame(
            'https://abc.ngrok-free.dev',
            \baseUrl(['HTTP_HOST' => 'abc.ngrok-free.dev', 'HTTP_X_FORWARDED_PROTO' => 'https'])
        );
    }

    public function testRedirectsLocalhostToDottedHost()
    {
        $this->assertSame(
            'http://127.0.0.1:8000/checkout.php?x=1',
            \dottedHostRedirect(['HTTP_HOST' => 'localhost:8000', 'REQUEST_URI' => '/checkout.php?x=1'])
        );
        $this->assertNull(\dottedHostRedirect(['HTTP_HOST' => '127.0.0.1:8000', 'REQUEST_URI' => '/']));
    }

    public function testApplePayDomainFromEnvOrHost()
    {
        $this->assertSame('shop.example', \applePayDomain(['OPAYO_APPLE_PAY_DOMAIN' => 'shop.example'], []));
        $this->assertSame('abc.ngrok-free.dev', \applePayDomain([], ['HTTP_HOST' => 'abc.ngrok-free.dev']));
    }

    public function testPostedMethod()
    {
        $this->assertSame('card', \postedMethod(['card-identifier' => 'CI']));
        $this->assertSame('googlepay', \postedMethod(['googlePayToken' => '{}']));
        $this->assertSame('applepay', \postedMethod(['applePayToken' => '{}']));
        $this->assertSame('paypal', \postedMethod(['method' => 'paypal']));
        $this->assertNull(\postedMethod([]));
        $this->assertNull(\postedMethod(['card-identifier' => '']));
    }

    public function testTwoCredentialsAreRefused()
    {
        $this->assertNull(\postedMethod(['card-identifier' => 'CI', 'googlePayToken' => '{}']));
    }

    public function testOrderStoreRemembersTheTransactionForAnOrder()
    {
        $dir = sys_get_temp_dir() . '/opayo-pi-test-' . bin2hex(random_bytes(4));

        $this->assertNull(\recallTransaction($dir, 'ORDER-1'));

        \rememberTransaction($dir, 'ORDER-1', 'TX-1');
        \rememberTransaction($dir, 'ORDER-2', 'TX-2');

        $this->assertSame('TX-1', \recallTransaction($dir, 'ORDER-1'));
        $this->assertSame('TX-2', \recallTransaction($dir, 'ORDER-2'));
        $this->assertNull(\recallTransaction($dir, '../ORDER-1'));

        array_map('unlink', glob($dir . '/*'));
        rmdir($dir);
    }

    public function testClientIpIsTheConnectingAddressWhenNotProxied()
    {
        $this->assertSame('203.0.113.9', \clientIp(['REMOTE_ADDR' => '203.0.113.9']));
    }

    public function testClientIpBehindLocalTunnelIsTheForwardedAddress()
    {
        $this->assertSame(
            '198.51.100.7',
            \clientIp(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'])
        );
        $this->assertSame(
            '198.51.100.7',
            \clientIp(['REMOTE_ADDR' => '::1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'])
        );
    }

    public function testClientIpTakesTheOriginalClientFromAProxyChain()
    {
        $this->assertSame(
            '198.51.100.7',
            \clientIp(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7, 10.0.0.5'])
        );
    }

    public function testClientIpIgnoresForwardedHeaderFromAnyoneButTheLocalProxy()
    {
        // A shopper connecting directly could send any X-Forwarded-For they like.
        $this->assertSame(
            '203.0.113.9',
            \clientIp(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'])
        );
    }

    public function testClientIpIgnoresAForwardedValueThatIsNotAnAddress()
    {
        $this->assertSame(
            '127.0.0.1',
            \clientIp(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => 'not-an-ip'])
        );
    }

    public function testInlineScriptCannotBeClosedEarlyByItsContent()
    {
        $file = tempnam(sys_get_temp_dir(), 'js');
        file_put_contents($file, "/* see </script> */ var a = '</SCRIPT>';");

        $html = \inlineScript($file);
        unlink($file);

        $this->assertStringStartsWith('<script>', $html);
        $this->assertStringEndsWith('</script>', $html);
        // Only the closing tag the helper adds itself.
        $this->assertSame(1, substr_count(strtolower($html), '</script'));
    }

    public function testEscapes()
    {
        $this->assertSame('&lt;a href=&quot;x&quot;&gt;', \h('<a href="x">'));
        $this->assertSame('', \h(null));
    }
}
