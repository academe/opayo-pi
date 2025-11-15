<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Integration;

use Academe\Opayo\Pi\Request\CreateSessionKey;
use Academe\Opayo\Pi\Response\SessionKey;
use Academe\Opayo\Pi\Factory\ResponseFactory;
use Academe\Opayo\Pi\Model\Auth;

/**
 * Integration test for creating session keys with real Opayo API.
 *
 * This test demonstrates:
 * - How to write an integration test
 * - Creating a merchant session key
 * - Handling API responses
 * - Using the IntegrationTestCase base class
 *
 * To run this test:
 *   vendor/bin/phpunit --testsuite=integration
 *
 * Or run just this test:
 *   vendor/bin/phpunit tests/Integration/SessionKeyTest.php
 */
class SessionKeyTest extends IntegrationTestCase
{
    /**
     * Test creating a merchant session key.
     *
     * This is a fundamental operation - session keys are required for
     * tokenizing cards on the client side.
     *
     * @group integration
     * @group session-key
     */
    public function testCreateSessionKey(): void
    {
        // Create the session key request
        $request = new CreateSessionKey(
            $this->endpoint,
            $this->auth,
            $_ENV['OPAYO_VENDOR_NAME']
        );

        // Send the request
        $httpClient = $this->getHttpClient();
        $httpResponse = $httpClient->sendRequest($request);

        // Parse the response
        $response = ResponseFactory::fromHttpResponse($httpResponse);

        // Assert we got a SessionKey response
        $this->assertInstanceOf(SessionKey::class, $response);

        // Assert the response is successful
        $this->assertResponseSuccessful($httpResponse);

        // Assert we received a merchant session key
        $merchantSessionKey = $response->getMerchantSessionKey();
        $this->assertNotNull($merchantSessionKey);
        $this->assertIsString($merchantSessionKey);

        // Session keys should be UUIDs wrapped in braces
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $merchantSessionKey,
            'Merchant session key should be a UUID in braces'
        );

        // Assert expiry is set
        $expiry = $response->getExpiry();
        $this->assertNotNull($expiry);
        $this->assertInstanceOf(\DateTimeInterface::class, $expiry);

        // Expiry should be in the future
        $now = new \DateTime();
        $this->assertGreaterThan(
            $now,
            $expiry,
            'Session key expiry should be in the future'
        );

        // Output key details for debugging (only shown if test fails or with --verbose)
        echo "\n";
        echo "Merchant Session Key: $merchantSessionKey\n";
        echo "Expires at: " . $expiry->format('Y-m-d H:i:s') . "\n";
    }

    /**
     * Test that invalid credentials result in an error.
     *
     * This verifies error handling works correctly.
     *
     * @group integration
     * @group error-handling
     */
    public function testCreateSessionKeyWithInvalidCredentials(): void
    {
        // Create auth with invalid credentials
        $invalidAuth = new Auth(
            'InvalidVendor',
            'InvalidKey',
            'InvalidPassword'
        );

        // Create the request with invalid auth
        $request = new CreateSessionKey(
            $this->endpoint,
            $invalidAuth,
            'InvalidVendor'
        );

        // Send the request
        $httpClient = $this->getHttpClient();
        $httpResponse = $httpClient->sendRequest($request);

        // Should get a 4xx error
        $statusCode = $httpResponse->getStatusCode();
        $this->assertGreaterThanOrEqual(400, $statusCode);
        $this->assertLessThan(500, $statusCode);

        // Parse error response
        $response = ResponseFactory::fromHttpResponse($httpResponse);

        // Should be an error
        $this->assertTrue($response->isError());
    }
}
