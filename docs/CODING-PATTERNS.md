# Opayo Pi - Coding Patterns & Conventions

This document describes the architectural patterns, coding conventions, and best practices used throughout the Opayo Pi PHP library.

> **⚡ KEY PATTERN REMINDER:** Always use **constructor property promotion** for simple property assignments. This is a core pattern in this codebase. See [Constructor Property Promotion](#constructor-property-promotion--always-prefer-this) section below.

## Table of Contents

1. [PHP 8.1+ Modernization Patterns](#php-81-modernization-patterns)
2. [Architecture Patterns](#architecture-patterns)
3. [PSR Compliance](#psr-compliance)
4. [Fluent API Pattern](#fluent-api-pattern)
5. [Value Objects & Immutability](#value-objects--immutability)
6. [Type System Usage](#type-system-usage)
7. [Error Handling](#error-handling)
8. [Documentation Standards](#documentation-standards)
9. [Domain-Specific Patterns](#domain-specific-patterns)

---

## PHP 8.1+ Modernization Patterns

### Strict Type Declarations

**All PHP files** must start with strict type declarations:

```php
<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Example;
```

**Why:** Ensures type safety and catches type-related bugs at runtime.

### Constructor Property Promotion ⚡ ALWAYS PREFER THIS

**IMPORTANT:** Constructor property promotion should be used whenever possible. This is a core pattern in this codebase and significantly reduces boilerplate code.

```php
// ✅ GOOD: Modern constructor promotion
class GooglePayPayment implements PaymentMethodInterface
{
    public function __construct(
        protected string $clientIpAddress,
        protected string $payload
    ) {
    }
}

// ✅ GOOD: With optional parameters
class ApplePayPayment implements PaymentMethodInterface
{
    public function __construct(
        protected string $clientIpAddress,
        protected string $payload,
        protected ?string $sessionValidationToken = null
    ) {
    }
}

// ✅ GOOD: With readonly for immutability
class MoneyAmount implements AmountInterface
{
    public function __construct(
        protected readonly Money $money
    ) {
    }
}

// ❌ BAD: Traditional property declaration - DO NOT USE THIS
class OldStyle
{
    protected string $clientIpAddress;
    protected string $payload;

    public function __construct(string $clientIpAddress, string $payload)
    {
        $this->clientIpAddress = $clientIpAddress;
        $this->payload = $payload;
    }
}
```

**When to use constructor property promotion:**
- ✅ **Simple assignment** - Property is directly assigned from parameter
- ✅ **Value objects** - Classes that hold data
- ✅ **Payment methods** - All PaymentMethodInterface implementations
- ✅ **With `readonly`** - For immutable objects
- ✅ **With default values** - For optional parameters
- ✅ **Multiple properties** - Even with 5+ properties

**When NOT to use (exceptions only):**
- ❌ **Complex initialization** - Properties need transformation or validation
- ❌ **Conditional logic** - Different assignment based on conditions
- ❌ **Dependencies** - One property depends on another's value

**Examples where NOT to use:**
```php
// Complex initialization - needs validation
public function __construct(string $code)
{
    $this->allCurrencies = new ISO4217();

    if (!$this->allCurrencies->getByAlpha3($code)) {
        throw new UnexpectedValueException(sprintf('Unsupported currency code "%s"', $code));
    }

    $this->code = $code;
}

// Property transformation needed
public function __construct(string $key, string $password)
{
    $this->integrationKey = new SensitiveValue($key);
    $this->integrationPassword = new SensitiveValue($password);
}
```

**Rule of Thumb:** If you're writing `$this->property = $parameter;` in the constructor body, you should be using constructor property promotion instead.

### Typed Properties

All class properties must have explicit type declarations:

```php
// Scalar types
protected string $vendorTxCode;
protected int $amount;
protected bool $giftAid = false;

// Nullable types
protected ?string $entryMethod = null;
protected ?AddressInterface $shippingAddress = null;

// Union types
protected int|string|null $httpCode;

// Array types
protected array $resource_path = [];
```

### Return Type Declarations

All methods must have explicit return type declarations:

```php
public function getAmount(): string
{
    return $this->money->getAmount();
}

public function isValid(): bool
{
    return ! empty($this->getPaRes());
}

public function getCurrency(): ?CurrencyInterface
{
    return $this->currency;
}

// Use static for fluent API
public function withAuth(Auth $auth): static
{
    $clone = clone $this;
    return $clone->setAuth($auth);
}

// Use mixed for flexible returns
public function jsonSerialize(): mixed
{
    return ['data' => $this->data];
}
```

### Union Types

Use union types instead of mixed when specific types are known:

```php
// ✅ GOOD: Specific union type
public function parseDateTime(string|DateTime|int $date): DateTime

// ✅ GOOD: Use mixed for truly flexible data
public function setData(mixed $data): mixed

// ❌ AVOID: Overly permissive
public function handle($data)
```

### Constant Visibility

All class constants must have explicit visibility:

```php
// ✅ GOOD: Explicit visibility
public const TRANSACTION_TYPE_PAYMENT = 'Payment';
public const STATUS_OK = 'Ok';
protected const INTERNAL_FLAG = 'internal';

// ❌ AVOID: Implicit public visibility
const OLD_STYLE = 'value';
```

### Enums for Fixed Value Sets ⚡ PREFER FOR NEW CODE

**IMPORTANT:** Use PHP 8.1+ enums for representing fixed sets of values (status codes, types, modes, etc.). Enums provide type safety, autocomplete, and encapsulate domain logic.

```php
// ✅ EXCELLENT: Backed enum with helper methods
enum TransactionStatus: string
{
    case OK = 'Ok';
    case NOT_AUTHED = 'NotAuthed';
    case REJECTED = 'Rejected';
    case THREE_D_AUTH = '3DAuth';
    case ERROR = 'Error';

    public function isSuccess(): bool
    {
        return $this === self::OK;
    }

    public function requiresAuthentication(): bool
    {
        return $this === self::THREE_D_AUTH;
    }

    public function description(): string
    {
        return match ($this) {
            self::OK => 'Transaction successful',
            self::NOT_AUTHED => 'Transaction not authenticated',
            self::REJECTED => 'Transaction rejected by bank',
            self::THREE_D_AUTH => '3D Secure authentication required',
            self::ERROR => 'Transaction error',
        };
    }
}

// ✅ GOOD: Using enum in classes
class Transaction
{
    public function __construct(
        protected TransactionStatus $status
    ) {
    }

    public function getStatus(): TransactionStatus
    {
        return $this->status;
    }

    public function isSuccessful(): bool
    {
        return $this->status->isSuccess();
    }
}
```

**Backwards Compatibility Pattern:**

When migrating from constants to enums, maintain backwards compatibility:

```php
// Define enum first
enum TransactionStatus: string
{
    case OK = 'Ok';
    case NOT_AUTHED = 'NotAuthed';
    // ... other cases
}

// Keep constants for BC, referencing enum values
class AbstractTransaction
{
    /**
     * @deprecated Use TransactionStatus enum instead
     */
    public const STATUS_OK = TransactionStatus::OK->value;
    public const STATUS_NOTAUTHED = TransactionStatus::NOT_AUTHED->value;

    // Store as enum internally
    protected ?TransactionStatus $statusEnum = null;

    // Getter returns enum (preferred)
    public function getStatusEnum(): ?TransactionStatus
    {
        return $this->statusEnum;
    }

    // Getter returns string (backwards compatibility)
    public function getStatusString(): ?string
    {
        return $this->statusEnum?->value;
    }
}
```

**When to use enums:**
- ✅ **Fixed API values** - Transaction statuses, payment methods, etc.
- ✅ **Configuration options** - Entry methods, challenge window sizes
- ✅ **Internal types** - Instruction types, credential types
- ✅ **Domain concepts** - When the set of values has business meaning

**Benefits:**
- 🎯 Type safety and IDE autocomplete
- 🔍 Exhaustive match expression checking
- 📚 Self-documenting code
- 🛠️ Encapsulate domain logic in helper methods
- ♻️ Easy refactoring across codebase

See [ENUM-MIGRATION-GUIDE.md](ENUM-MIGRATION-GUIDE.md) for detailed examples and migration strategies.

---

## Architecture Patterns

### Abstract Base Classes

The library uses abstract base classes to define common behavior:

```php
abstract class AbstractRequest extends AbstractMessage
{
    protected ?Endpoint $endpoint = null;
    protected ?Auth $auth = null;

    abstract protected function setData(mixed $data): mixed;

    // Template method pattern
    public function getUrl(): string
    {
        return $this->getEndpoint()->getUrl($this->getResourcePath());
    }
}
```

**Key Abstract Classes:**
- `AbstractMessage` - Base for all messages
- `AbstractRequest` - Base for API requests
- `AbstractResponse` - Base for API responses
- `AbstractTransaction` - Base for transaction responses
- `AbstractCollection` - Base for collection responses
- `AbstractInstruction` - Base for payment instructions

### Trait Usage

Traits provide reusable functionality:

```php
abstract class AbstractRequest implements RequestInterface
{
    use RequestPsr7Trait;  // PSR-7 HTTP message methods
}
```

**RequestPsr7Trait** implements PSR-7 RequestInterface methods.

### Interface Segregation

Small, focused interfaces define contracts:

```php
interface AmountInterface
{
    public function getAmount(): string;
    public function getCurrencyCode(): string;
}

interface PersonInterface
{
    public function withFieldPrefix(string $prefix): static;
    public function getNamesBody(): array;
}
```

### Native PSR-7 Implementation

The library includes lightweight, native implementations of PSR-7 interfaces with zero external dependencies:

```php
use Academe\Opayo\Pi\Http\Stream;
use Academe\Opayo\Pi\Http\Uri;

// Create a stream from a string
$stream = new Stream('{"vendorTxCode": "12345"}');

// Create a URI from a string
$uri = new Uri('https://pi-test.sagepay.com/api/v1/transactions');

// Request classes use these internally
public function getBody(): StreamInterface
{
    $body = json_encode($this);
    return new Stream($body);
}

public function getUri(): UriInterface
{
    return new Uri($this->getUrl());
}
```

**Benefits:**
- Zero dependencies for PSR-7 stream/URI creation
- Simple, focused implementations
- Full PSR-7 compliance
- No factory complexity needed

---

## PSR Compliance

### PSR-7: HTTP Messages

All requests and responses implement PSR-7 interfaces:

```php
class AbstractRequest implements RequestInterface
{
    // PSR-7 methods: getMethod(), getUri(), getHeaders(), etc.
}

class AbstractResponse
{
    public static function fromHttpResponse(ResponseInterface $response)
    {
        // Parse PSR-7 response
    }
}
```

### PSR-12: Extended Coding Style

- 4 spaces for indentation (no tabs)
- Opening braces `{` on same line for methods
- Visibility required on all properties and methods
- Strict types declaration on separate line

---

## Fluent API Pattern

### Immutability with Clone

Create immutable objects using clone pattern:

```php
// ✅ GOOD: Immutable with* methods
public function withAuth(Auth $auth): static
{
    $clone = clone $this;
    return $clone->setAuth($auth);
}

public function withDescription(string $description): static
{
    $copy = clone $this;
    return $copy->setDescription($description);
}
```

### Mutable vs Immutable Methods

- **`set*()` methods** - Mutable, return `$this` or `self`, usually `protected`
- **`with*()` methods** - Immutable, return `static`, always `public`

```php
// Mutable (internal use)
protected function setGiftAid(bool $giftAid): static
{
    $this->giftAid = ! empty($giftAid);
    return $this;
}

// Immutable (public API)
public function withGiftAid(bool $giftAid): static
{
    $copy = clone $this;
    return $copy->setGiftAid($giftAid);
}
```

### Fluent API Usage

```php
$payment = (new CreatePayment($endpoint, $auth, ...))
    ->withEntryMethod(CreatePayment::ENTRY_METHOD_ECOMMERCE)
    ->withGiftAid(true)
    ->withApply3DSecure(CreatePayment::APPLY_3D_SECURE_FORCE);
```

---

## Value Objects & Immutability

### Value Object Pattern

Value objects are immutable and defined by their attributes:

```php
class Amount implements AmountInterface
{
    public function __construct(
        protected readonly CurrencyInterface $currency,
        protected readonly int|string $amount
    ) {
        // Validation in constructor
        if (! is_numeric($this->amount)) {
            throw new UnexpectedValueException(...);
        }
    }

    // No setters - immutable!
}
```

**Key Value Objects:**
- `Amount` - Money amount with currency
- `Currency` - ISO currency code
- `Auth` - Authentication credentials
- `Endpoint` - API endpoint configuration
- `Address` - Billing/shipping address
- `Person` - Customer/recipient details

### Readonly Properties

Use `readonly` for truly immutable properties:

```php
class MoneyAmount implements AmountInterface
{
    public function __construct(
        protected readonly Money $money  // Cannot be modified after construction
    ) {
    }
}
```

---

## Type System Usage

### Nullable Types

Use `?Type` for nullable parameters and returns:

```php
public function __construct(?ServerRequestInterface $message = null)
{
    if (isset($message)) {
        $this->setData($this->parseBody($message));
    }
}

public function getThreeDSSessionData(): ?string
{
    return $this->threeDSSessionData;
}
```

### Union Types for Flexibility

Use union types when multiple specific types are valid:

```php
// Multiple types accepted
public static function parseDateTime(string|DateTime|int $date): DateTime
{
    if (is_string($date)) {
        return new DateTime($date);
    } elseif ($date instanceof DateTime) {
        return $date;
    } elseif (is_int($date)) {
        $datetime = new DateTime();
        $datetime->setTimestamp($date);
        return $datetime;
    }
}
```

### Mixed Type

Use `mixed` for truly polymorphic data:

```php
// Abstract method with flexible data
abstract protected function setData(mixed $data): mixed;

// JSON serialization
public function jsonSerialize(): mixed
{
    return [
        'amount' => $this->getAmount(),
        'currency' => $this->getCurrencyCode(),
    ];
}
```

### Static Return Type

Use `static` for late static binding in inheritance:

```php
public static function fromData(mixed $data): static
{
    $instance = new static();  // Creates instance of child class
    return $instance->setData($data);
}

public function withOptions(array $options = []): static
{
    $copy = clone $this;
    return $copy->setOptions($options);
}
```

---

## Error Handling

### Exception Hierarchy

Use specific exception types:

```php
use UnexpectedValueException;

if (! $value) {
    throw new UnexpectedValueException(sprintf(
        'Unknown entryMethod "%s"; require one of %s',
        (string)$entryMethod,
        implode(', ', static::getEntryMethods())
    ));
}
```

### Error Collections

API errors are wrapped in ErrorCollection:

```php
public static function fromHttpResponse(ResponseInterface $response): static|ErrorCollection
{
    $httpCode = $response->getStatusCode();
    $data = static::parseBody($response);

    if ($httpCode >= Http::BAD_REQUEST || Helper::dataGet($data, 'errors')) {
        // Return error collection instead of throwing
        return ErrorCollection::fromHttpResponse($response);
    }

    return static::fromData($data, $httpCode);
}
```

### Validation in Constructors

Validate value objects at construction time:

```php
public function __construct(
    protected readonly CurrencyInterface $currency,
    protected readonly int|string $amount
) {
    if (! is_numeric($this->amount)) {
        throw new UnexpectedValueException(sprintf(
            'Amount "%s" must be numeric',
            $this->amount
        ));
    }
}
```

---

## Documentation Standards

### DocBlock Guidelines

Remove redundant docblocks that only repeat type information:

```php
// ❌ AVOID: Redundant docblock
/**
 * @param string $description
 * @return static
 */
public function withDescription(string $description): static

// ✅ GOOD: Meaningful documentation
/**
 * Get the fields (names and values) to go into the paReq POST.
 * MD = Merchant Data; it is generated by the merchant site...
 *
 * @param string|null $termUrl The callback URL, if known at this point
 * @param string|null $md The Merchant Data, if known at this point
 */
public function getPaRequestFields(?string $termUrl = null, ?string $md = null): array
```

### When to Keep DocBlocks

Keep docblocks when they add value:

1. **Complex array structures**
```php
/**
 * @return array Array of error mappings: {code, property, message, clientMessage}
 */
public static function readErrorPropertyMap(): array
```

2. **Business logic explanation**
```php
/**
 * Parse a date, returning a DateTime.
 * Handles ISO 8601 format with microseconds and nanoseconds.
 * Sage Pay sometimes returns nano-second precision which DateTime cannot handle.
 */
public static function parseDateTime(string|DateTime|int $date): DateTime
```

3. **Deprecated features**
```php
// @deprecated removed from the API spec 2023-10-26
public const APPLY_3D_SECURE_FORCEIGNORINGRULES = 'ForceIgnoringRules';
```

4. **Class-level documentation**
```php
/**
 * The transaction value object to send a transaction to Sage Pay.
 * See https://test.sagepay.com/documentation/#transactions
 */
class CreatePayment extends AbstractRequest
```

---

## Domain-Specific Patterns

### 3D Secure Authentication Flow

```php
// Request classes
CreateSecure3D          // 3D Secure v1 authentication
CreateSecure3Dv2Challenge // 3D Secure v2 challenge

// Response classes
Secure3DRedirect        // v1 redirect to ACS
Secure3Dv2Redirect      // v2 redirect to ACS

// Server request classes (callbacks)
Secure3DAcs             // v1 callback from bank
Secure3Dv2Notification  // v2 callback from bank
```

### Payment Transaction Lifecycle

```php
// Initial payment request
CreatePayment          // POST /transactions

// Transaction types
TRANSACTION_TYPE_PAYMENT   = 'Payment'
TRANSACTION_TYPE_REPEAT    = 'Repeat'
TRANSACTION_TYPE_REFUND    = 'Refund'
TRANSACTION_TYPE_DEFERRED  = 'Deferred'

// Transaction instructions
CreateVoid    // Cancel before settlement
CreateAbort   // Cancel deferred transaction
CreateRelease // Release deferred funds
CreateRefund  // Refund settled transaction
```

### Session Key Management

```php
// Merchant session key flow
FetchSessionKey    // GET /merchant-session-keys/{key}
CreateSessionKey   // POST /merchant-session-keys

// Session key is used to encrypt card data on client side
```

### Constant Value Mapping

Use constant mapping for enumerated values:

```php
// Define constants
public const ENTRY_METHOD_ECOMMERCE = 'Ecommerce';
public const ENTRY_METHOD_MAILORDER = 'MailOrder';

// Helper method to get all values
public static function getEntryMethods(): array
{
    return static::constantList('ENTRY_METHOD');
}

// Validation against constants
public function setEntryMethod(string $entryMethod): static
{
    $value = $this->constantValue('ENTRY_METHOD', $entryMethod);

    if (! $value) {
        throw new UnexpectedValueException(...);
    }

    $this->entryMethod = $value;
    return $this;
}
```

### Resource Path Substitution

Dynamic URL generation with placeholders:

```php
// Define resource path with placeholder
protected array $resource_path = ['transactions', '{transactionId}', 'instructions'];

// Getter provides the value
public function getTransactionId(): string
{
    return $this->transactionId;
}

// Framework substitutes {transactionId} with getTransactionId() result
// transactions/ABC123/instructions
```

### Sensitive Data Handling

```php
class SensitiveValue
{
    // Wrapper to prevent accidental logging of sensitive data
    // toString/jsonSerialize returns masked value
}
```

---

## Testing Patterns

### Test Structure

Tests use PHPUnit 10:

```bash
./vendor/bin/phpunit
```

### Test Organization

- Unit tests in `tests/` directory
- Mirror source structure
- One test class per source class

### Test Data

Static test data in `data/` directory:
- `error-maps.json` - Error code mappings

---

## Common Patterns Summary

### Property Declaration Pattern

```php
// 1. Constructor-promoted (readonly value objects)
public function __construct(
    protected readonly Money $money
) {
}

// 2. Typed properties (complex objects)
protected PaymentMethodInterface $paymentMethod;
protected ?string $entryMethod = null;
protected bool $giftAid = false;

// 3. Array properties
protected array $resource_path = ['transactions'];
```

### Method Pattern

```php
// 1. Immutable with* (public)
public function withAuth(Auth $auth): static
{
    $clone = clone $this;
    return $clone->setAuth($auth);
}

// 2. Mutable set* (protected/public)
protected function setAuth(Auth $auth): self
{
    $this->auth = $auth;
    return $this;
}

// 3. Getters (public)
public function getAuth(): ?Auth
{
    return $this->auth;
}

// 4. Boolean checks (public)
public function isValid(): bool
{
    return ! empty($this->getData());
}
```

### Static Factory Pattern

```php
public static function fromData(mixed $data): static
{
    $instance = new static();
    return $instance->setData($data);
}

public static function fromHttpResponse(ResponseInterface $response): static|ErrorCollection
{
    // Parse response and create appropriate object
}
```

### Serialization Pattern

```php
public function jsonSerialize(): mixed
{
    return [
        'field1' => $this->field1,
        'field2' => $this->field2,
    ];
}
```

---

## Quick Reference Checklist

When creating a new class, ensure:

- [ ] `declare(strict_types=1);` at top
- [ ] **⚡ Constructor uses property promotion** (unless complex initialization needed)
- [ ] **⚡ Use enums for fixed value sets** (statuses, types, modes)
- [ ] All properties have type declarations
- [ ] All methods have return types
- [ ] All constants have `public const` visibility
- [ ] Immutable methods use `clone` and return `static`
- [ ] Value objects use `readonly` where appropriate
- [ ] DocBlocks only when adding meaningful information
- [ ] Use union types over `mixed` when types are known
- [ ] Abstract methods declared in base classes
- [ ] Factory methods return `static` for inheritance
- [ ] PSR-7 interfaces implemented correctly
- [ ] Validation in constructor for value objects
- [ ] Tests created/updated

---

## Migration Checklist (From Legacy Code)

Converting old code to modern PHP 8.1+:

1. [ ] Add `declare(strict_types=1);`
2. [ ] **⚡ Convert simple constructors to property promotion** (high priority!)
3. [ ] Convert properties to typed properties
4. [ ] Change `const` to `public const`
5. [ ] Add return types to all methods
6. [ ] Add parameter types to all methods
7. [ ] Add `readonly` to immutable properties
8. [ ] Change `@return self` to `: self` or `: static`
9. [ ] Remove redundant docblocks
10. [ ] Modernize array syntax `array()` → `[]`
11. [ ] Use union types instead of `|null` in docblocks
12. [ ] Run tests to verify changes

---

**Last Updated:** 2025-11
**PHP Version:** 8.1+
**Maintainer:** Academe
