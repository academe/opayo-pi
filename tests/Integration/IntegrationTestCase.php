<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Integration;

use PHPUnit\Framework\TestCase;
use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;

/**
 * Base class for integration tests that require Opayo API credentials.
 *
 * Integration tests are skipped if credentials are not available in environment.
 * To run integration tests:
 *
 * 1. Copy .env.example to .env
 * 2. Fill in your Opayo test account credentials
 * 3. Run: vendor/bin/phpunit --testsuite=integration
 *
 * For CI/CD, only unit tests run by default (no credentials needed).
 */
abstract class IntegrationTestCase extends TestCase
{
    protected ?Auth $auth = null;
    protected ?Endpoint $endpoint = null;

    /**
     * Set up integration test - checks for credentials and creates auth/endpoint.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Load .env file if it exists (for local development)
        $this->loadEnvFile();

        // Check if credentials are available
        if (!$this->hasCredentials()) {
            $this->markTestSkipped(
                'Opayo integration test credentials not available. ' .
                'Copy .env.example to .env and add your test account credentials to run integration tests.'
            );
        }

        // Create auth and endpoint for tests
        $this->auth = $this->createAuth();
        $this->endpoint = $this->createEndpoint();
    }

    /**
     * Check if Opayo credentials are available in environment.
     *
     * @return bool True if all required credentials are set and non-empty
     */
    protected function hasCredentials(): bool
    {
        $vendorName = $_ENV['OPAYO_VENDOR_NAME'] ?? '';
        $integrationKey = $_ENV['OPAYO_INTEGRATION_KEY'] ?? '';
        $integrationPassword = $_ENV['OPAYO_INTEGRATION_PASSWORD'] ?? '';

        return $vendorName !== '' && $integrationKey !== '' && $integrationPassword !== '';
    }

    /**
     * Create Auth object from environment credentials.
     *
     * @return Auth
     */
    protected function createAuth(): Auth
    {
        return new Auth(
            $_ENV['OPAYO_VENDOR_NAME'],
            $_ENV['OPAYO_INTEGRATION_KEY'],
            $_ENV['OPAYO_INTEGRATION_PASSWORD']
        );
    }

    /**
     * Create Endpoint object from environment configuration.
     *
     * @return Endpoint
     */
    protected function createEndpoint(): Endpoint
    {
        $environment = $_ENV['OPAYO_ENVIRONMENT'] ?? 'test';

        return new Endpoint(
            $environment === 'live' ? Endpoint::MODE_LIVE : Endpoint::MODE_TEST
        );
    }

    /**
     * Load environment variables from .env file if it exists.
     *
     * This provides a simple .env loader for local development.
     * In production/CI, environment variables should be set externally.
     *
     * @return void
     */
    protected function loadEnvFile(): void
    {
        $envFile = __DIR__ . '/../../.env';

        if (!file_exists($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            // Skip comments
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            // Parse KEY=VALUE format
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Only set if not already in environment or if current value is empty
                if ($key !== '' && (!isset($_ENV[$key]) || $_ENV[$key] === '')) {
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
    }

    /**
     * Get a PSR-18 HTTP client for making requests.
     *
     * This is a helper method. Subclasses can override to use different clients.
     * Requires a PSR-18 client package to be installed.
     *
     * @return \Psr\Http\Client\ClientInterface
     */
    protected function getHttpClient(): \Psr\Http\Client\ClientInterface
    {
        // Check if Guzzle 7 is available
        if (class_exists(\GuzzleHttp\Client::class)) {
            return new \GuzzleHttp\Client([
                'timeout' => 30,
                'http_errors' => false, // Don't throw on 4xx/5xx
            ]);
        }

        throw new \RuntimeException(
            'No PSR-18 HTTP client available. Install guzzlehttp/guzzle:^7.0 or another PSR-18 client.'
        );
    }

    /**
     * Assert that a response is successful (2xx status code).
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param string $message
     * @return void
     */
    protected function assertResponseSuccessful(
        \Psr\Http\Message\ResponseInterface $response,
        string $message = ''
    ): void {
        $statusCode = $response->getStatusCode();
        $this->assertGreaterThanOrEqual(
            200,
            $statusCode,
            $message ?: "Expected successful response, got $statusCode"
        );
        $this->assertLessThan(
            300,
            $statusCode,
            $message ?: "Expected successful response, got $statusCode"
        );
    }
}
