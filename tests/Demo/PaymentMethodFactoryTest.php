<?php

namespace Academe\Opayo\Pi\Demo;

use Academe\Opayo\Pi\Request\Model\SingleUseCard;
use Academe\Opayo\Pi\Request\Model\GooglePayPayment;
use Academe\Opayo\Pi\Request\Model\ApplePayPayment;
use Academe\Opayo\Pi\Request\Model\PayPalPayment;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../demo/shared.php';

/**
 * paymentMethodFromRequest() is the single point in the demo where the payment
 * method matters: it turns whatever one credential the browser posted into a
 * PaymentMethodInterface. Everything after it in pay.php is identical for all
 * methods.
 */
class PaymentMethodFactoryTest extends TestCase
{
    public function testCardIdentifierMakesSingleUseCard()
    {
        $m = paymentMethodFromRequest(['card-identifier' => 'CI-1'], 'MSK-1', '10.0.0.1');

        $this->assertInstanceOf(SingleUseCard::class, $m);
    }

    public function testGoogleTokenMakesGooglePayPayment()
    {
        $m = paymentMethodFromRequest(['googlePayToken' => 'tok'], 'MSK-1', '10.0.0.1');

        $this->assertInstanceOf(GooglePayPayment::class, $m);
        // fromGoogleToken base64-encodes the token into the payload.
        $this->assertSame(base64_encode('tok'), $m->jsonSerialize()['googlePay']['payload']);
    }

    public function testAppleTokenMakesApplePayPayment()
    {
        $token = json_encode([
            'paymentData' => ['x' => 1],
            'paymentMethod' => [],
            'transactionIdentifier' => 't',
        ]);

        $m = paymentMethodFromRequest(
            ['applePayToken' => $token, 'appleSessionValidationToken' => 'SVT'],
            'MSK-1',
            '10.0.0.1'
        );

        $this->assertInstanceOf(ApplePayPayment::class, $m);
    }

    public function testPaypalMethodMakesPayPalPayment()
    {
        $m = paymentMethodFromRequest(['method' => 'paypal'], 'MSK-1', '10.0.0.1');

        $this->assertInstanceOf(PayPalPayment::class, $m);
    }

    public function testNothingThrows()
    {
        $this->expectException(\InvalidArgumentException::class);

        paymentMethodFromRequest([], 'MSK-1', '10.0.0.1');
    }

    public function testCardIdentifierWinsWhenSeveralArePresent()
    {
        // Defensive: the panels post exactly one, but if two arrive the card
        // identifier is taken first and deterministically.
        $m = paymentMethodFromRequest(
            ['card-identifier' => 'CI-1', 'googlePayToken' => 'tok'],
            'MSK-1',
            '10.0.0.1'
        );

        $this->assertInstanceOf(SingleUseCard::class, $m);
    }
}
