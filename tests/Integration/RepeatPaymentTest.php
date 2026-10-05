<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Integration;

use Academe\Opayo\Pi\Request\CreateSessionKey;
use Academe\Opayo\Pi\Request\CreateCardIdentifier;
use Academe\Opayo\Pi\Request\CreatePayment;
use Academe\Opayo\Pi\Request\CreateRepeatPayment;
use Academe\Opayo\Pi\Request\Model\Address;
use Academe\Opayo\Pi\Request\Model\CredentialType;
use Academe\Opayo\Pi\Request\Model\Person;
use Academe\Opayo\Pi\Request\Model\SingleUseCard;
use Academe\Opayo\Pi\Request\Model\StrongCustomerAuthentication;
use Academe\Opayo\Pi\Request\Enums\BrowserColorDepth;
use Academe\Opayo\Pi\Request\Enums\ChallengeWindowSize;
use Academe\Opayo\Pi\Request\Enums\ThreeDSExemptionIndicator;
use Academe\Opayo\Pi\Request\Enums\TransType;
use Academe\Opayo\Pi\Response\CardIdentifier;
use Academe\Opayo\Pi\Response\Payment;
use Academe\Opayo\Pi\Response\Repeat;
use Academe\Opayo\Pi\Response\SessionKey;
use Academe\Opayo\Pi\Factory\ResponseFactory;
use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Money\Currency;

/**
 * Integration test for repeat payments with the real Opayo API.
 *
 * A Repeat transaction requires the credentialType object
 * (cofUsage Subsequent, initiatedType MIT), and the original payment it
 * references must itself have carried credentialType with cofUsage First
 * and initiatedType CIT to register the credential on file.
 *
 * To run this test:
 *   vendor/bin/phpunit --testsuite=integration
 *
 * Or run just this test:
 *   vendor/bin/phpunit tests/Integration/RepeatPaymentTest.php
 */
class RepeatPaymentTest extends IntegrationTestCase
{
    private const TEST_CARD_VISA = '4929000000006';
    private const TEST_CARD_CVV = '123';

    /**
     * Complete repeat payment flow:
     * 1. Create a base payment flagged for credential-on-file reuse
     *    (credentialType First/CIT).
     * 2. Repeat against its transactionId with credentialType
     *    Subsequent/MIT (CredentialType::createForRepeatPayment()).
     *
     * @group integration
     * @group repeat-payment
     */
    public function testRepeatAgainstReusablePayment(): void
    {
        // Step 1: The base payment, registered for later reuse.
        $baseResponse = $this->createBasePayment();

        if (! $baseResponse->isSuccessful()) {
            $this->markTestSkipped(
                'Base payment was not authorised (status: '
                . $baseResponse->getStatus() . '); cannot test a repeat against it.'
            );
        }

        $baseTransactionId = $baseResponse->getTransactionId();
        $this->assertNotNull($baseTransactionId, 'Base payment must return a transaction ID');

        // Give the gateway a moment before referencing the transaction.
        usleep(1000000); // 1 second

        // Step 2: Repeat against the base transaction.
        // Keep under the 40 character vendorTxCode limit.
        $vendorTxCode = 'TEST-RPT-' . uniqid() . '-' . time();
        $amount = (new Amount(Currency::GBP(), 0))->withMajorUnit('4.99');

        $request = new CreateRepeatPayment(
            $this->endpoint,
            $this->auth,
            $baseTransactionId,
            $vendorTxCode,
            $amount, // Not limited by the original amount.
            'Test Repeat Payment',
            null,
            null,
            [
                'credentialType' => CredentialType::createForRepeatPayment(),
            ]
        );

        $httpClient = $this->getHttpClient();
        $httpResponse = $httpClient->sendRequest($request);
        $response = ResponseFactory::fromHttpResponse($httpResponse);

        $statusCode = $httpResponse->getStatusCode();

        echo "\n";
        echo "Repeat payment test completed\n";
        echo "HTTP Status Code: $statusCode\n";
        echo "Response Status: " . $response->getStatus() . "\n";

        if ($response->isError()) {
            $body = (string)$httpResponse->getBody();
            echo "Response body: " . substr($body, 0, 500) . "\n";
        }

        $this->assertInstanceOf(
            Repeat::class,
            $response,
            'Response should be a Repeat object'
        );

        $this->assertTrue(
            $response->isSuccessful(),
            'Repeat payment should be successful. Status: ' . $response->getStatus()
        );

        $repeatTransactionId = $response->getTransactionId();
        $this->assertNotNull($repeatTransactionId, 'Repeat should return its own transaction ID');
        $this->assertNotSame(
            $baseTransactionId,
            $repeatTransactionId,
            'Repeat transaction ID should differ from the base transaction'
        );

        $responseAmount = $response->getAmount();
        $this->assertNotNull($responseAmount);
        $this->assertEquals(
            499,
            $responseAmount->getTotal()->getAmount(),
            'Repeat amount should match the requested amount (in minor units)'
        );

        echo "Base Transaction ID: $baseTransactionId\n";
        echo "Repeat Transaction ID: $repeatTransactionId\n";
    }

    /**
     * Create the base payment carrying credentialType First/CIT so its
     * credential is registered on file for the repeat.
     */
    private function createBasePayment(): Payment
    {
        [$sessionKey, $cardIdentifier] = $this->createCardIdentifier(self::TEST_CARD_VISA);

        // Keep under the 40 character vendorTxCode limit.
        $vendorTxCode = 'TEST-RPT-B-' . uniqid() . '-' . time();
        $amount = (new Amount(Currency::GBP(), 0))->withMajorUnit('9.99');

        $customer = new Person('Sam', 'Jones', 'sam.jones@example.com', '07700900000');
        $billingAddress = new Address('88', '88 Avenue Road', 'London', 'EC2A 4DP', 'GB');
        $paymentMethod = new SingleUseCard($sessionKey, $cardIdentifier);

        $request = new CreatePayment(
            $this->endpoint,
            $this->auth,
            $paymentMethod,
            $vendorTxCode,
            $amount,
            'Test Repeat Base Payment',
            $billingAddress,
            $customer,
            options: [
                'entryMethod' => CreatePayment::ENTRY_METHOD_ECOMMERCE,
                'apply3DSecure' => CreatePayment::APPLY_3D_SECURE_DISABLE,
                'credentialType' => CredentialType::createForNewReusableCard(),
                // A CIT credentialType requires the full strongCustomerAuthentication
                // object, and bypassing 3D Secure requires an exemption indicator.
                'strongCustomerAuthentication' => new StrongCustomerAuthentication(
                    'https://example.com/3ds-notification',
                    '203.0.113.10',
                    '*/*',
                    true,
                    'en-GB',
                    'Mozilla/5.0 (Integration Test)',
                    ChallengeWindowSize::Small,
                    TransType::GoodsAndServicePurchase,
                    [
                        'browserJavaEnabled' => false,
                        'browserColorDepth' => BrowserColorDepth::Depth24,
                        'browserScreenHeight' => 1080,
                        'browserScreenWidth' => 1920,
                        'browserTz' => 0,
                        'threeDsExemptionIndicator' => ThreeDSExemptionIndicator::TransactionRiskAnalysis,
                    ]
                ),
            ]
        );

        $httpClient = $this->getHttpClient();
        $httpResponse = $httpClient->sendRequest($request);

        $this->assertResponseSuccessful(
            $httpResponse,
            'Base payment must succeed for the repeat payment test'
        );

        $response = ResponseFactory::fromHttpResponse($httpResponse);
        $this->assertInstanceOf(Payment::class, $response);

        return $response;
    }

    /**
     * Tokenize a test card: create a session key, then a card identifier.
     *
     * @return array{0: string, 1: string} [sessionKey, cardIdentifier]
     */
    private function createCardIdentifier(string $cardNumber): array
    {
        $sessionKeyRequest = new CreateSessionKey(
            $this->endpoint,
            $this->auth,
            $_ENV['OPAYO_VENDOR_NAME']
        );

        $httpClient = $this->getHttpClient();
        $httpResponse = $httpClient->sendRequest($sessionKeyRequest);

        $this->assertResponseSuccessful(
            $httpResponse,
            'Session key creation must succeed for repeat payment test'
        );

        $sessionKeyResponse = ResponseFactory::fromHttpResponse($httpResponse);
        $this->assertInstanceOf(SessionKey::class, $sessionKeyResponse);

        $sessionKey = $sessionKeyResponse->getMerchantSessionKey();
        $this->assertNotNull($sessionKey);

        // Small delay to avoid rate limiting.
        usleep(500000); // 0.5 seconds

        $cardRequest = new CreateCardIdentifier(
            $this->endpoint,
            $this->auth,
            $sessionKey,
            'Test Cardholder',
            $cardNumber,
            date('my', strtotime('+2 years')), // Any future expiry, MMYY.
            self::TEST_CARD_CVV
        );

        $httpResponse = $httpClient->sendRequest($cardRequest);

        $this->assertResponseSuccessful(
            $httpResponse,
            'Card identifier creation must succeed for repeat payment test'
        );

        $cardResponse = ResponseFactory::fromHttpResponse($httpResponse);
        $this->assertInstanceOf(CardIdentifier::class, $cardResponse);

        $cardIdentifier = $cardResponse->getCardIdentifier();
        $this->assertNotNull($cardIdentifier);

        return [$sessionKey, $cardIdentifier];
    }
}
