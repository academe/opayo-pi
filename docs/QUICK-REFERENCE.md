# Opayo Pi - Quick Reference Guide

A quick reference for common patterns and tasks in the Opayo Pi library.

## Documentation Index

- **[CODING-PATTERNS.md](CODING-PATTERNS.md)** - Detailed coding conventions and patterns
- **[PROJECT-ARCHITECTURE.md](PROJECT-ARCHITECTURE.md)** - Architecture overview and design patterns
- **[QUICK-REFERENCE.md](QUICK-REFERENCE.md)** - This file (quick lookup)

---

## Essential PHP 8.1+ Patterns

### File Header
```php
<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\YourNamespace;
```

### Property Declaration
```php
// Constructor promotion
public function __construct(
    protected readonly Money $money,    // Immutable
    protected string $name,              // Mutable
    protected ?int $age = null,          // Nullable with default
) {
}

// Traditional (when logic needed)
protected PaymentMethodInterface $paymentMethod;
protected ?string $entryMethod = null;
protected bool $giftAid = false;
```

### Method Signatures
```php
// Getter
public function getAmount(): string

// Boolean check
public function isValid(): bool

// Nullable return
public function getAuth(): ?Auth

// Mutable setter (protected, returns self)
protected function setAuth(Auth $auth): self

// Immutable with* (public, returns static)
public function withAuth(Auth $auth): static
{
    $clone = clone $this;
    return $clone->setAuth($auth);
}

// Static factory
public static function fromData(mixed $data): static

// JSON serialization
public function jsonSerialize(): mixed
```

### Constants
```php
public const TRANSACTION_TYPE_PAYMENT = 'Payment';
public const STATUS_OK = 'Ok';
protected const INTERNAL_CONSTANT = 'internal';
```

---

## Class Structure Template

```php
<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Example;

use Academe\Opayo\Pi\AbstractClass;
use SomeInterface;

class ExampleClass extends AbstractClass implements SomeInterface
{
    // Constants
    public const STATUS_ACTIVE = 'active';

    // Properties (typed)
    protected string $property;
    protected ?int $optional = null;

    // Constructor
    public function __construct(
        protected readonly string $immutableProperty,
        string $mutableProperty
    ) {
        $this->property = $mutableProperty;
        $this->validate();
    }

    // Static factory
    public static function fromArray(array $data): static
    {
        return new static(
            $data['immutable'],
            $data['mutable']
        );
    }

    // Getters
    public function getProperty(): string
    {
        return $this->property;
    }

    // Boolean checks
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    // Mutable setters (protected)
    protected function setProperty(string $value): self
    {
        $this->property = $value;
        return $this;
    }

    // Immutable methods (public)
    public function withProperty(string $value): static
    {
        $clone = clone $this;
        return $clone->setProperty($value);
    }

    // Serialization
    public function jsonSerialize(): mixed
    {
        return [
            'immutable' => $this->immutableProperty,
            'mutable' => $this->property,
        ];
    }

    // Validation (private)
    private function validate(): void
    {
        if (empty($this->property)) {
            throw new \InvalidArgumentException('Property cannot be empty');
        }
    }
}
```

---

## Common Patterns

### Value Object
```php
class Amount implements AmountInterface
{
    public function __construct(
        protected readonly CurrencyInterface $currency,
        protected readonly int|string $amount
    ) {
        if (! is_numeric($this->amount)) {
            throw new UnexpectedValueException('Amount must be numeric');
        }
    }

    public function getAmount(): string
    {
        return (string) $this->amount;
    }

    public function getCurrencyCode(): string
    {
        return $this->currency->getCode();
    }
}
```

### Fluent Builder
```php
$payment = (new CreatePayment($endpoint, $auth, $paymentMethod, ...))
    ->withEntryMethod(CreatePayment::ENTRY_METHOD_ECOMMERCE)
    ->withGiftAid(true)
    ->withApply3DSecure(CreatePayment::APPLY_3D_SECURE_FORCE);
```

### Abstract Base Class
```php
abstract class AbstractRequest extends AbstractMessage
{
    protected ?Endpoint $endpoint = null;

    // Template method
    public function getUrl(): string
    {
        return $this->getEndpoint()->getUrl($this->getResourcePath());
    }

    // Hook for subclasses
    abstract protected function getResourcePath(): array;
}
```

### Static Factory
```php
public static function fromData(mixed $data): static
{
    if (is_string($data)) {
        $data = json_decode($data, true);
    }

    return new static($data);
}

public static function fromHttpResponse(ResponseInterface $response): static
{
    $data = Helper::parseBody($response);
    return static::fromData($data, $response->getStatusCode());
}
```

### Error Handling
```php
// Constructor validation
public function __construct(string $code)
{
    if (strlen($code) !== 3) {
        throw new UnexpectedValueException('Currency code must be 3 characters');
    }

    $this->code = $code;
}

// Constant validation
public function setMethod(string $method): self
{
    $value = $this->constantValue('METHOD', $method);

    if (! $value) {
        throw new UnexpectedValueException(sprintf(
            'Unknown method "%s"; require one of %s',
            $method,
            implode(', ', static::getMethods())
        ));
    }

    $this->method = $value;
    return $this;
}
```

---

## Type System Quick Reference

```php
// Basic types
string $name
int $age
bool $active
float $amount
array $items

// Nullable
?string $optional
?DateTime $date

// Union types
int|string $flexible
string|DateTime|int $multi

// Mixed (any type)
mixed $data

// Return types
: void          // No return
: self          // Current class
: static        // Late static binding
: never         // Never returns (throws)

// Readonly
protected readonly string $immutable;
```

---

## Testing Quick Reference

### Run Tests
```bash
# All tests
./vendor/bin/phpunit

# Specific test
./vendor/bin/phpunit tests/Money/AmountTest.php

# With coverage
./vendor/bin/phpunit --coverage-html coverage/
```

### Test Structure
```php
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testSomething(): void
    {
        $object = new Example('value');

        $this->assertEquals('value', $object->getValue());
        $this->assertTrue($object->isValid());
        $this->assertNull($object->getOptional());
    }
}
```

---

## Payment Flow Quick Reference

### Standard Payment
```php
// 1. Create session key
$sessionKeyRequest = new CreateSessionKey($endpoint, $auth);
$sessionKey = $gateway->send($sessionKeyRequest);

// 2. Use session key in JavaScript to encrypt card
// (Client-side with Opayo.js)

// 3. Create payment with encrypted card
$payment = new CreatePayment(
    $endpoint,
    $auth,
    $encryptedCard,
    $vendorTxCode,
    $amount,
    $description,
    $billingAddress,
    $customer
);

$response = $gateway->send($payment);

// 4. Handle response
if ($response->isSuccess()) {
    // Payment complete
} elseif ($response->isRedirect()) {
    // Redirect to 3D Secure
    $acsUrl = $response->getAcsUrl();
    // ... redirect user
} elseif ($response->isError()) {
    // Handle errors
    foreach ($response->getErrors() as $error) {
        echo $error->getDescription();
    }
}
```

### 3D Secure Callback
```php
// User returns from bank
$notification = new Secure3DAcs($_POST);

if ($notification->isValid()) {
    $paRes = $notification->getPaRes();
    $md = $notification->getMD();

    // Send to Opayo for verification
    // ... complete transaction
}
```

### Deferred Payment
```php
// 1. Authorize
$deferredRequest = new CreateDeferred(/* ... */);
$response = $gateway->send($deferredRequest);

// 2a. Capture later
$releaseRequest = new CreateRelease($transactionId, $amount);
$gateway->send($releaseRequest);

// 2b. Or cancel
$abortRequest = new CreateAbort($transactionId);
$gateway->send($abortRequest);
```

---

## Common Helpers

### Helper Class
```php
use Academe\Opayo\Pi\Helper;

// Get nested data
$value = Helper::dataGet($data, 'customer.address.postCode', 'default');

// Parse datetime
$date = Helper::parseDateTime('2025-01-01T12:00:00.000Z');

// Parse HTTP message body
$data = Helper::parseBody($psrMessage);

// Get error map
$errorMap = Helper::readErrorPropertyMap();
```

### ISO 3166
```php
use Academe\Opayo\Pi\Iso3166\Countries;
use Academe\Opayo\Pi\Iso3166\States;

// Validate country
$isValid = Countries::isValid('GB');  // true

// Get all countries
$countries = Countries::getAll();

// Check if country has states
$hasStates = States::hasStates('US');  // true

// Validate state
$isValid = States::isValid('US', 'CA');  // true (California)
```

---

## Constant Patterns

### Define Constants
```php
class PaymentMethod
{
    public const TYPE_CARD = 'Card';
    public const TYPE_PAYPAL = 'PayPal';
    public const TYPE_APPLE_PAY = 'ApplePay';
}
```

### Get All Constants
```php
public static function getTypes(): array
{
    return static::constantList('TYPE');  // Returns ['Card', 'PayPal', 'ApplePay']
}
```

### Validate Against Constants
```php
public function setType(string $type): self
{
    $value = $this->constantValue('TYPE', $type);

    if (! $value) {
        throw new UnexpectedValueException(
            'Unknown type: ' . $type
        );
    }

    $this->type = $value;
    return $this;
}
```

---

## DocBlock Guidelines

### When to Include DocBlocks

```php
// ✅ DO include for complex structures
/**
 * Get the fields (names and values) to go into the paReq POST.
 * MD = Merchant Data; it is generated by the merchant site...
 *
 * @return array List of parameter fields and values: ['name' => 'value', ...]
 */
public function getPaRequestFields(): array

// ✅ DO include for business logic
/**
 * Parse a date, returning a DateTime.
 * Handles ISO 8601 format with microseconds and nanoseconds.
 * Sage Pay sometimes returns nano-second precision which DateTime cannot handle.
 */
public static function parseDateTime(string|DateTime|int $date): DateTime

// ❌ DON'T include for simple type-documented methods
public function getAmount(): string  // Type is self-documenting
```

---

## Git Workflow

### Commit Messages
```bash
# Format: Title + Body
git commit -m "$(cat <<'EOF'
Short descriptive title

- Bullet point 1
- Bullet point 2
- Bullet point 3
EOF
)"
```

### Branch Naming
```
claude/feature-name-{session-id}
claude/repo-maintenance-{session-id}
```

---

## Migration Checklist

When modernizing legacy code:

```
[ ] Add declare(strict_types=1);
[ ] Type all properties
[ ] Add public to all constants
[ ] Add return types to all methods
[ ] Add parameter types
[ ] Convert simple constructors to promotion
[ ] Add readonly to immutable properties
[ ] Remove redundant docblocks
[ ] Modernize array() to []
[ ] Use union types
[ ] Run tests
```

---

## Common Commands

```bash
# Install dependencies
composer install

# Run tests
./vendor/bin/phpunit

# Run tests with coverage
./vendor/bin/phpunit --coverage-html coverage/

# Check syntax
php -l src/**/*.php

# Static analysis (if installed)
vendor/bin/phpstan analyse

# Code style (if installed)
vendor/bin/php-cs-fixer fix
```

---

## Links

- [Full Coding Patterns](CODING-PATTERNS.md)
- [Project Architecture](PROJECT-ARCHITECTURE.md)
- [Opayo API Docs](https://developer.opayo.com/)
- [PHP 8.1 Documentation](https://www.php.net/releases/8.1/)

---

**Last Updated:** 2025-11
