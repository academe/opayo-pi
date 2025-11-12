# Testing Guide

This document explains the testing structure for the Opayo Pi library, including both unit tests and integration tests.

## Table of Contents

1. [Test Structure](#test-structure)
2. [Running Tests](#running-tests)
3. [Unit Tests](#unit-tests)
4. [Integration Tests](#integration-tests)
5. [Writing Tests](#writing-tests)
6. [CI/CD Configuration](#cicd-configuration)

---

## Test Structure

The test suite is organized into two main categories:

### Unit Tests (Default)

- **Location**: `tests/` (excluding `tests/Integration/`)
- **Purpose**: Fast, isolated tests with no external dependencies
- **Requirements**: None - run everywhere
- **CI/CD**: Always run automatically
- **Speed**: Very fast (~0.1s)

### Integration Tests

- **Location**: `tests/Integration/`
- **Purpose**: Test against real Opayo test API
- **Requirements**: Opayo test account credentials
- **CI/CD**: Optional - only if credentials provided
- **Speed**: Slower (~2-5s per test)

---

## Running Tests

### Run Unit Tests Only (Default)

```bash
# Run all unit tests (no credentials needed)
vendor/bin/phpunit

# Or explicitly specify unit suite
vendor/bin/phpunit --testsuite=unit
```

This is the default and runs in CI/CD automatically.

### Run Integration Tests Only

```bash
# Run only integration tests (requires credentials)
vendor/bin/phpunit --testsuite=integration
```

Integration tests are automatically **skipped** if credentials are not available.

### Run All Tests

```bash
# Run both unit and integration tests
vendor/bin/phpunit --testsuite=all
```

### Run Specific Test File

```bash
# Run a specific test file
vendor/bin/phpunit tests/Response/TransactionStatusTest.php

# Run a specific integration test
vendor/bin/phpunit tests/Integration/SessionKeyTest.php
```

### Run Tests with Coverage

```bash
# Generate HTML coverage report
vendor/bin/phpunit --coverage-html coverage/

# View in browser
open coverage/index.html
```

---

## Unit Tests

Unit tests are fast, isolated tests that don't require external services.

### Characteristics

- ✅ No API calls or network requests
- ✅ No database connections
- ✅ Use mocks/stubs for dependencies
- ✅ Fast execution (milliseconds)
- ✅ Deterministic results
- ✅ Run in CI/CD without setup

### Example Unit Test

```php
<?php

namespace Academe\Opayo\Pi\Response;

use PHPUnit\Framework\TestCase;

class TransactionStatusTest extends TestCase
{
    public function testEnumHasCorrectValue(): void
    {
        $this->assertEquals('Ok', TransactionStatus::OK->value);
    }

    public function testIsSuccessMethod(): void
    {
        $this->assertTrue(TransactionStatus::OK->isSuccess());
        $this->assertFalse(TransactionStatus::ERROR->isSuccess());
    }
}
```

### Writing Unit Tests

Unit tests should follow these guidelines:

1. **Test one thing** - Each test method should verify one behavior
2. **Use descriptive names** - `testGetStatusReturnsStringValue()`
3. **Arrange-Act-Assert** - Setup, execute, verify
4. **No external dependencies** - Mock or stub anything external
5. **Fast execution** - Should complete in milliseconds

---

## Integration Tests

Integration tests verify the library works correctly with the real Opayo API.

### Setup for Local Development

**Step 1: Get Opayo Test Credentials**

1. Sign up for a test account at [Opayo Test Environment](https://test.opayo.eu.elavon.com/)
2. Get your credentials:
   - Vendor Name
   - Integration Key
   - Integration Password

**Step 2: Configure Credentials**

```bash
# Copy the example file
cp .env.example .env

# Edit .env and add your credentials
nano .env
```

**.env file:**
```bash
OPAYO_VENDOR_NAME=YourVendorName
OPAYO_INTEGRATION_KEY=your-integration-key
OPAYO_INTEGRATION_PASSWORD=your-integration-password
OPAYO_ENVIRONMENT=test
```

**Step 3: Run Integration Tests**

```bash
vendor/bin/phpunit --testsuite=integration
```

### What Gets Tested

Integration tests verify:

- ✅ Session key creation (`SessionKeyTest`)
- ✅ Card tokenization and identifier creation (`CardIdentifierTest`)
- ✅ Payment transaction processing (`PaymentTest`)
- ✅ Multiple currencies (GBP, USD)
- ✅ Shipping address handling
- ✅ 3D Secure flows
- ✅ Error handling with invalid data
- ✅ API response parsing

### Opayo Test Cards

Integration tests use official Opayo test card numbers. These cards are designed for testing and will not process real transactions:

| Card Type | Card Number | CVV | Expiry |
|-----------|-------------|-----|--------|
| **Visa** | `4929000000006` | `123` | Any future date (MMYY) |
| **MasterCard** | `5404000000000001` | `123` | Any future date |
| **Visa Debit** | `4462000000000003` | `123` | Any future date |
| **American Express** | `374200000000004` | `1234` | Any future date |

**Important Notes:**
- These are official Opayo test cards - they will NOT work in production
- Use CVV `123` for all cards except AmEx (use `1234`)
- Any future expiry date works (format: MMYY)
- These cards trigger specific test behaviors in the Opayo test environment
- All other card numbers will be rejected as invalid

**Example Usage:**
```php
// Valid test card for integration tests
$request = new CreateCardIdentifier(
    $endpoint,
    $auth,
    $sessionKey,
    'Test Cardholder',
    '4929000000006',  // Visa test card
    '1225',           // December 2025
    '123'             // CVV
);
```

### Available Integration Tests

**`tests/Integration/SessionKeyTest.php`**
- Creates merchant session keys
- Tests authentication
- Validates session key format and expiry

**`tests/Integration/CardIdentifierTest.php`**
- Tokenizes test cards to get card identifiers
- Tests with Visa and MasterCard
- Verifies error handling with invalid cards
- Tests CVV optional scenarios

**`tests/Integration/PaymentTest.php`**
- Complete payment flow from card tokenization to payment
- Tests successful payments with Visa and MasterCard
- Tests with different currencies (GBP, USD)
- Tests with shipping address and recipient
- Verifies error handling (zero amount)
- Validates transaction IDs and amounts in responses

### Example Integration Test

```php
<?php

namespace Academe\Opayo\Pi\Tests\Integration;

class SessionKeyTest extends IntegrationTestCase
{
    public function testCreateSessionKey(): void
    {
        // Create request
        $request = new CreateSessionKey(
            $this->endpoint,  // Provided by base class
            $this->auth,      // Provided by base class
            $_ENV['OPAYO_VENDOR_NAME']
        );

        // Send to API
        $httpClient = $this->getHttpClient();
        $response = $httpClient->sendRequest($request);

        // Parse and verify
        $sessionKey = ResponseFactory::fromHttpResponse($response);
        $this->assertInstanceOf(SessionKey::class, $sessionKey);
    }
}
```

### Writing Integration Tests

Integration tests should:

1. **Extend `IntegrationTestCase`** - Base class handles credentials
2. **Use `@group` annotations** - Tag with `@group integration`
3. **Handle API delays** - Use appropriate timeouts
4. **Clean up resources** - Delete test transactions if needed
5. **Be idempotent** - Can run multiple times safely

### Integration Test Base Class

The `IntegrationTestCase` base class provides:

```php
// Protected properties available in tests
$this->auth;      // Auth object from environment
$this->endpoint;  // Endpoint object (test or live)

// Protected methods
$this->getHttpClient();              // PSR-18 HTTP client
$this->assertResponseSuccessful();   // Assert 2xx status
```

Tests are **automatically skipped** if credentials are not available:

```
S............................  (S = Skipped)

There was 1 skipped test:

1) SessionKeyTest::testCreateSessionKey
Opayo integration test credentials not available.
Copy .env.example to .env and add your test account credentials.
```

---

## Writing Tests

### Test File Structure

```php
<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Tests\{Category};

use PHPUnit\Framework\TestCase;

class MyClassTest extends TestCase
{
    public function testSomeBehavior(): void
    {
        // Arrange
        $instance = new MyClass();

        // Act
        $result = $instance->doSomething();

        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

### Test Naming Conventions

```php
// ✅ Good: Descriptive, behavior-focused
testGetStatusReturnsEnumWhenSet()
testIsSuccessfulReturnsTrueForOkStatus()
testConstructorThrowsExceptionForInvalidCurrency()

// ❌ Bad: Unclear what's being tested
testStatus()
testMethod1()
testError()
```

### Assertion Best Practices

```php
// ✅ Use specific assertions
$this->assertSame($expected, $actual);          // Identity (===)
$this->assertEquals($expected, $actual);        // Equality (==)
$this->assertInstanceOf(MyClass::class, $obj); // Type check
$this->assertNull($value);                      // Null check

// ❌ Avoid generic assertions
$this->assertTrue($a === $b);  // Use assertSame instead
$this->assertTrue(is_null($x)); // Use assertNull instead
```

### Data Providers

For testing multiple inputs:

```php
/**
 * @dataProvider statusProvider
 */
public function testStatusParsing(string $input, TransactionStatus $expected): void
{
    $status = TransactionStatus::tryFromInsensitive($input);
    $this->assertSame($expected, $status);
}

public static function statusProvider(): array
{
    return [
        'ok lowercase' => ['ok', TransactionStatus::OK],
        'ok uppercase' => ['OK', TransactionStatus::OK],
        'ok mixed' => ['Ok', TransactionStatus::OK],
    ];
}
```

---

## CI/CD Configuration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    strategy:
      matrix:
        php: ['8.1', '8.2', '8.3']

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: xdebug

      - name: Install dependencies
        run: composer install --prefer-dist

      - name: Run unit tests
        run: vendor/bin/phpunit --testsuite=unit

      - name: Run integration tests (if credentials available)
        if: env.OPAYO_VENDOR_NAME != ''
        env:
          OPAYO_VENDOR_NAME: ${{ secrets.OPAYO_VENDOR_NAME }}
          OPAYO_INTEGRATION_KEY: ${{ secrets.OPAYO_INTEGRATION_KEY }}
          OPAYO_INTEGRATION_PASSWORD: ${{ secrets.OPAYO_INTEGRATION_PASSWORD }}
        run: vendor/bin/phpunit --testsuite=integration
```

### GitLab CI Example

```yaml
test:
  image: php:8.1
  script:
    - composer install
    - vendor/bin/phpunit --testsuite=unit

test:integration:
  image: php:8.1
  script:
    - composer install
    - vendor/bin/phpunit --testsuite=integration
  only:
    variables:
      - $OPAYO_VENDOR_NAME
```

### Travis CI Example

```yaml
language: php

php:
  - 8.1
  - 8.2
  - 8.3

install:
  - composer install

script:
  - vendor/bin/phpunit --testsuite=unit
  # Integration tests only if credentials set
  - if [ -n "$OPAYO_VENDOR_NAME" ]; then vendor/bin/phpunit --testsuite=integration; fi
```

---

## Best Practices

### DO

- ✅ Write unit tests for all public methods
- ✅ Use integration tests sparingly (slow, flaky)
- ✅ Keep tests fast and focused
- ✅ Use descriptive test names
- ✅ Test edge cases and error conditions
- ✅ Use data providers for multiple inputs
- ✅ Clean up after integration tests
- ✅ Run unit tests before every commit

### DON'T

- ❌ Commit `.env` file (credentials!)
- ❌ Write integration tests for simple logic
- ❌ Test private methods directly
- ❌ Share state between tests
- ❌ Hard-code test credentials
- ❌ Ignore failed tests
- ❌ Skip writing tests for bug fixes

---

## Troubleshooting

### Integration Tests Are Skipped

**Problem**: Tests show as "S" (skipped)

**Solution**:
1. Check `.env` file exists and has correct values
2. Verify credentials are correct
3. Run with `--verbose` to see skip reason:
   ```bash
   vendor/bin/phpunit --testsuite=integration --verbose
   ```

### "No PSR-18 HTTP client available" Error

**Problem**: Integration tests fail with HTTP client error

**Solution**: Guzzle 7 is included in `require-dev` and should be installed automatically when running `composer install`. If you still get this error, ensure dev dependencies are installed:
```bash
composer install --dev
```

Note: Library users can choose any PSR-18 client they want. Guzzle is only required for running integration tests during development.

### Tests Are Slow

**Problem**: Test suite takes too long

**Solution**:
1. Run only unit tests by default (fast)
2. Run integration tests manually when needed
3. Use `--filter` to run specific tests:
   ```bash
   vendor/bin/phpunit --filter testStatusEnum
   ```

### Tests Fail Randomly

**Problem**: Integration tests occasionally fail

**Causes**:
- Network timeouts
- API rate limiting
- Test account issues

**Solutions**:
- Increase HTTP client timeout
- Add retry logic for network failures
- Check Opayo test environment status

---

## Summary

- **Unit tests** = Fast, always run, no setup
- **Integration tests** = Slow, manual run, requires credentials
- **Local development** = Use `.env` for credentials
- **CI/CD** = Only unit tests by default
- **Test coverage** = Aim for 80%+ on critical code

Run this before every commit:
```bash
vendor/bin/phpunit --testsuite=unit
```

Run this before major releases:
```bash
vendor/bin/phpunit --testsuite=all
```

---

**Last Updated:** 2025-11
**PHPUnit Version:** 10.5+
**PHP Version:** 8.1+
