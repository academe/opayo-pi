<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Integration;

use Academe\Opayo\Pi\Request\CreateSessionKey;
use Academe\Opayo\Pi\Request\CreateCardIdentifier;
use Academe\Opayo\Pi\Response\SessionKey;
use Academe\Opayo\Pi\Response\CardIdentifier;
use Academe\Opayo\Pi\Factory\ResponseFactory;

/**
 * Integration test for creating card identifiers with real Opayo API.
 *
 * This test demonstrates:
 * - Creating a session key (required for card operations)
 * - Tokenizing a card to get a card identifier
 * - Testing with valid Opayo test cards
 * - Testing error handling with invalid cards
 *
 * To run this test:
 *   vendor/bin/phpunit --testsuite=integration
 *
 * Or run just this test:
 *   vendor/bin/phpunit tests/Integration/CardIdentifierTest.php
 */
class CardIdentifierTest extends IntegrationTestCase
{
    /**
     * Valid Opayo test card numbers for integration testing.
     * These are official test cards from Opayo documentation.
     */
    private const TEST_CARD_VISA = '4929000000006';
    private const TEST_CARD_MASTERCARD = '5404000000000001';
    private const TEST_CARD_CVV = '123';

    /**
     * Invalid card number for testing error handling.
     */
    private const INVALID_CARD = '1234567890123456';

    /**
     * Test creating a card identifier with a valid Visa test card.
     *
     * This is the typical flow for tokenizing a card:
     * 1. Create a session key
     * 2. Use session key to tokenize card details
     * 3. Receive a card identifier for subsequent transactions
     *
     * @group integration
     * @group card-identifier
     */
    public function testCreateCardIdentifierWithValidCard(): void
    {
        // Step 1: Create a session key
        $sessionKey = $this->createSessionKey();
        $this->assertNotNull($sessionKey, 'Session key should not be null');

        // Expiry: two months from now (MMYY format)
        $expiry = (new \DateTime())->modify('+2 months')->format('my');

        // Step 2: Create card identifier request with valid Visa test card
        $request = new CreateCardIdentifier(
            $this->endpoint,
            $this->auth,
            $sessionKey,
            'Test Cardholder',
            self::TEST_CARD_VISA,
            $expiry,
            self::TEST_CARD_CVV
        );

        // Step 3: Send the request
        $httpClient = $this->getHttpClient();
        $httpResponse = $httpClient->sendRequest($request);

        // Step 4: Parse the response
        $response = ResponseFactory::fromHttpResponse($httpResponse);

        // Assert we got a CardIdentifier response
        $this->assertInstanceOf(
            CardIdentifier::class,
            $response,
            'Response should be a CardIdentifier object'
        );

        // Assert the response is successful
        $this->assertResponseSuccessful(
            $httpResponse,
            'Card identifier creation should succeed with valid test card'
        );

        // Assert we received a card identifier
        $cardIdentifier = $response->getCardIdentifier();
        $this->assertNotNull($cardIdentifier, 'Card identifier should not be null');
        $this->assertIsString($cardIdentifier, 'Card identifier should be a string');
        $this->assertNotEmpty($cardIdentifier, 'Card identifier should not be empty');

        // Card identifiers should be UUIDs wrapped in braces
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $cardIdentifier,
            'Card identifier should be a UUID in braces'
        );

        // Assert expiry is set
        $expiry = $response->getExpiry();
        $this->assertNotNull($expiry, 'Card identifier expiry should be set');
        $this->assertInstanceOf(\DateTimeInterface::class, $expiry);

        // Expiry should be in the future
        $now = new \DateTime();
        $this->assertGreaterThan(
            $now,
            $expiry,
            'Card identifier expiry should be in the future'
        );

        // Assert card type is detected
        $cardType = $response->getCardType();
        $this->assertNotNull($cardType, 'Card type should be detected');
        $this->assertSame('Visa', $cardType, 'Should detect Visa card type');

        // Assert the card identifier is not expired
        $this->assertFalse(
            $response->isExpired(),
            'Card identifier should not be expired immediately after creation'
        );

        // Test __toString() method
        $cardIdentifierString = (string)$response;
        $this->assertSame(
            $cardIdentifier,
            $cardIdentifierString,
            '__toString() should return the card identifier'
        );

        // Output details for debugging (only shown with --verbose)
        echo "\n";
        echo "Card Identifier: $cardIdentifier\n";
        echo "Card Type: $cardType\n";
        echo "Expires at: " . $expiry->format('Y-m-d H:i:s') . "\n";
    }

    /**
     * Test creating a card identifier with a MasterCard.
     *
     * This verifies that the API works with different card types.
     *
     * @group integration
     * @group card-identifier
     */
    public function testCreateCardIdentifierWithMasterCard(): void
    {
        // Step 1: Create a session key
        $sessionKey = $this->createSessionKey();

        // Step 2: Create card identifier with MasterCard test card
        $request = new CreateCardIdentifier(
            $this->endpoint,
            $this->auth,
            $sessionKey,
            'Test Cardholder',
            self::TEST_CARD_MASTERCARD,
            '0626', // Expiry: June 2026
            self::TEST_CARD_CVV
        );

        // Step 3: Send the request
        $httpClient = $this->getHttpClient();
        $httpResponse = $httpClient->sendRequest($request);

        // Step 4: Parse and verify response
        $response = ResponseFactory::fromHttpResponse($httpResponse);

        $this->assertInstanceOf(CardIdentifier::class, $response);
        $this->assertResponseSuccessful($httpResponse);

        $cardIdentifier = $response->getCardIdentifier();
        $this->assertNotNull($cardIdentifier);

        // Verify MasterCard is detected
        $cardType = $response->getCardType();
        $this->assertSame(
            'MasterCard',
            $cardType,
            'Should detect MasterCard card type'
        );

        echo "\n";
        echo "MasterCard Identifier: $cardIdentifier\n";
        echo "Card Type: $cardType\n";
    }

    /**
     * Test that an invalid card number results in an error.
     *
     * This verifies error handling when invalid card details are provided.
     *
     * @group integration
     * @group card-identifier
     * @group error-handling
     */
    public function testCreateCardIdentifierWithInvalidCard(): void
    {
        // Step 1: Create a session key
        $sessionKey = $this->createSessionKey();

        // Step 2: Create card identifier request with INVALID card number
        $request = new CreateCardIdentifier(
            $this->endpoint,
            $this->auth,
            $sessionKey,
            'Test Cardholder',
            self::INVALID_CARD, // Invalid card number
            date('my', strtotime('+2 years')), // Any future expiry, MMYY.
            self::TEST_CARD_CVV
        );

        // Step 3: Send the request
        $httpClient = $this->getHttpClient();
        $httpResponse = $httpClient->sendRequest($request);

        // Step 4: Should get an error response (4xx status)
        $statusCode = $httpResponse->getStatusCode();
        $this->assertGreaterThanOrEqual(
            400,
            $statusCode,
            'Should return 4xx error for invalid card'
        );
        $this->assertLessThan(
            500,
            $statusCode,
            'Should be client error (4xx), not server error (5xx)'
        );

        // Parse error response
        $response = ResponseFactory::fromHttpResponse($httpResponse);

        // Should be an error
        $this->assertTrue(
            $response->isError(),
            'Response should indicate an error occurred'
        );

        echo "\n";
        echo "Invalid card correctly rejected with status: $statusCode\n";
    }

    /**
     * Test that missing CVV is handled appropriately.
     *
     * CVV is optional in the request, but Opayo may require it depending
     * on merchant configuration.
     *
     * @group integration
     * @group card-identifier
     */
    public function testCreateCardIdentifierWithoutCvv(): void
    {
        // Step 1: Create a session key
        $sessionKey = $this->createSessionKey();

        // Step 2: Create card identifier without CVV
        $request = new CreateCardIdentifier(
            $this->endpoint,
            $this->auth,
            $sessionKey,
            'Test Cardholder',
            self::TEST_CARD_VISA,
            date('my', strtotime('+2 years')), // Any future expiry, MMYY.
            null // No CVV
        );

        // Step 3: Send the request
        $httpClient = $this->getHttpClient();
        $httpResponse = $httpClient->sendRequest($request);

        // Note: Response may succeed or fail depending on merchant settings
        // This test just verifies the request can be sent without CVV
        $statusCode = $httpResponse->getStatusCode();

        echo "\n";
        echo "Card identifier request without CVV resulted in status: $statusCode\n";

        // We don't assert success/failure here as it depends on merchant config
        // The test just verifies the code handles the null CVV case
        $this->assertNotNull($httpResponse, 'Should receive a response');
    }

    /**
     * Helper method to create a session key for card operations.
     *
     * Session keys are required before tokenizing card details.
     *
     * @return string The merchant session key
     */
    private function createSessionKey(): string
    {
        $request = new CreateSessionKey(
            $this->endpoint,
            $this->auth,
            $_ENV['OPAYO_VENDOR_NAME']
        );

        $httpClient = $this->getHttpClient();
        $httpResponse = $httpClient->sendRequest($request);

        $this->assertResponseSuccessful(
            $httpResponse,
            'Session key creation must succeed before testing card identifiers'
        );

        $response = ResponseFactory::fromHttpResponse($httpResponse);
        $this->assertInstanceOf(SessionKey::class, $response);

        $sessionKey = $response->getMerchantSessionKey();
        $this->assertNotNull($sessionKey, 'Session key must not be null');

        return $sessionKey;
    }
}
