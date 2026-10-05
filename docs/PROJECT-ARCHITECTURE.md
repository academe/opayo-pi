# Opayo Pi - Project Architecture & Learned Patterns

This document provides an overview of the Opayo Pi library architecture and the key patterns learned during the modernization and maintenance of this codebase.

## Project Overview

**Opayo Pi** is a PHP library for integrating with the Opayo (formerly Sage Pay) payment gateway REST API (Pi = Payment Integration).

- **Language:** PHP 8.1+
- **Architecture:** Object-oriented, PSR-compliant
- **Testing:** PHPUnit 10
- **Payment Gateway:** Opayo REST API
- **License:** MIT

---

## Directory Structure

```
opayo-pi/
├── src/                          # Source code
│   ├── Factory/                  # PSR-7 factories (Guzzle, Diactoros)
│   ├── Iso3166/                  # Country and state codes
│   ├── Model/                    # Core models (Auth, Endpoint)
│   ├── Money/                    # Money value objects
│   ├── Request/                  # API request objects
│   │   └── Model/               # Request data models
│   ├── Response/                 # API response objects
│   │   └── Model/               # Response data models
│   ├── Security/                 # Security utilities
│   ├── ServerRequest/           # Webhook/callback handlers
│   ├── AbstractMessage.php      # Base message class
│   └── Helper.php               # Utility functions
├── tests/                        # PHPUnit tests
├── data/                         # Static data (error maps)
├── docs/                         # Documentation
└── vendor/                       # Composer dependencies
```

---

## Core Architecture Layers

### 1. Message Layer (Base)

```
AbstractMessage
├── AbstractRequest (outgoing API calls)
│   ├── CreatePayment
│   ├── CreateSessionKey
│   ├── FetchTransaction
│   └── CreateVoid, CreateRefund, etc.
├── AbstractResponse (incoming API responses)
│   ├── Payment
│   ├── SessionKey
│   ├── ErrorCollection
│   └── Secure3DRedirect, etc.
└── AbstractServerRequest (webhook callbacks)
    ├── Secure3DAcs (3DS v1)
    └── Secure3Dv2Notification (3DS v2)
```

**Key Responsibilities:**
- Message serialization/deserialization
- PSR-7 HTTP message implementation
- Common helper methods

### 2. Domain Model Layer

Value objects representing business concepts:

```
Money/
├── Amount              # Monetary amount with currency
├── Currency            # ISO 4217 currency code
├── MoneyAmount         # moneyphp/money bridge (in: wrap Money; out: fromAmount()/toMoney())
└── AmountInterface     # Amount abstraction

Model/
├── Auth                # API credentials
├── Endpoint            # API endpoint configuration
└── Request/Model/
    ├── Address         # Billing/shipping address
    ├── Person          # Customer/recipient details
    ├── SingleUseCard   # Card for one-time payment
    ├── ReusableCard    # Saved card reference
    └── StrongCustomerAuthentication  # 3DS v2 data
```

**Key Characteristics:**
- Immutable value objects
- Constructor validation
- No business logic (pure data)
- Type-safe

### 3. Request Layer

API request builders with fluent interfaces:

```
Request/
├── CreatePayment              # New payment transaction
├── CreateSessionKey           # Get session key for card encryption
├── FetchTransaction           # Retrieve transaction details
├── CreateVoid                 # Cancel pre-settlement
├── CreateRefund               # Refund settled transaction
├── CreateDeferred             # Deferred payment authorization
├── CreateRelease              # Release deferred payment
├── CreateAbort                # Abort deferred payment
├── CreateSecure3D             # 3D Secure v1 flow
└── CreateSecure3Dv2Challenge  # 3D Secure v2 flow
```

**Request Flow:**
1. Build request object with fluent API
2. Serialize to PSR-7 HTTP request
3. Send via HTTP client (user-provided)
4. Parse PSR-7 HTTP response
5. Return response object

### 4. Response Layer

Parsed API responses:

```
Response/
├── Payment                    # Payment transaction result
├── Repeat                     # Repeat payment result
├── Deferred                   # Deferred authorization result
├── SessionKey                 # Merchant session key
├── CardIdentifier             # Tokenized card reference
├── Secure3DRedirect           # 3DS v1 redirect details
├── Secure3Dv2Redirect         # 3DS v2 redirect details
├── ErrorCollection            # Validation errors
└── AbstractTransaction        # Base for transaction responses
```

**Response Hierarchy:**
```
AbstractResponse
├── SessionKey (simple response)
├── NoContent (empty response)
├── ErrorCollection (errors)
└── AbstractTransaction (transactions)
    ├── Payment
    ├── Repeat
    ├── Deferred
    └── AbstractInstruction (post-transaction)
        ├── VoidInstruction
        ├── Release
        ├── Refund
        └── Abort
```

### 5. Server Request Layer

Handles webhook callbacks from payment gateway:

```
ServerRequest/
├── AbstractServerRequest      # Base webhook handler
├── Secure3DAcs                # 3D Secure v1 callback
└── Secure3Dv2Notification     # 3D Secure v2 callback
```

**Callback Flow:**
1. Opayo redirects user to bank (ACS)
2. User completes 3D Secure challenge
3. Bank redirects back to merchant with encrypted result
4. Merchant parses with `Secure3DAcs` or `Secure3Dv2Notification`
5. Merchant sends result to Opayo for verification

---

## Key Design Patterns

### 1. Template Method Pattern

Abstract base classes define algorithm structure:

```php
abstract class AbstractRequest
{
    // Template method
    public function getUrl(): string
    {
        return $this->getEndpoint()->getUrl($this->getResourcePath());
    }

    // Hook method (implemented by subclasses)
    abstract protected function getResourcePath(): array;
}
```

### 2. Factory Pattern

Create PSR-7 objects with automatic library detection:

```php
// Auto-detects Guzzle or Diactoros
$factory = $request->getFactory();
$psrRequest = $factory->createRequest('POST', $url);
```

### 3. Fluent Builder Pattern

Chain method calls for readability:

```php
$payment = (new CreatePayment($endpoint, $auth, $paymentMethod, ...))
    ->withEntryMethod(CreatePayment::ENTRY_METHOD_ECOMMERCE)
    ->withApply3DSecure(CreatePayment::APPLY_3D_SECURE_FORCE)
    ->withGiftAid(true);
```

### 4. Immutable Object Pattern

Create modified copies instead of mutating:

```php
// Returns new instance, original unchanged
$newPayment = $payment->withDescription('Updated description');
```

### 5. Static Factory Pattern

Alternative constructors for different input sources:

```php
// From array/object
$response = Payment::fromData($data, $httpCode);

// From PSR-7 response
$response = Payment::fromHttpResponse($psrResponse);

// From JSON string
$response = Payment::fromData($jsonString);
```

### 6. Native PSR-7 Implementation

The library provides its own lightweight PSR-7 stream and URI implementations:

```php
namespace Academe\Opayo\Pi\Http;

class Stream implements StreamInterface
{
    // Zero-dependency stream using php://temp
}

class Uri implements UriInterface
{
    // Zero-dependency URI using parse_url()
}

// Used internally by request classes:
$stream = new Stream($jsonBody);
$uri = new Uri($fullUrl);
```

### 7. Value Object Pattern

Immutable objects defined by their attributes:

```php
class Amount implements AmountInterface
{
    public function __construct(
        protected readonly CurrencyInterface $currency,
        protected readonly int|string $amount
    ) {
        // Validation
    }

    // No setters - immutable
}
```

### 8. Specification Pattern (Implicit)

Boolean methods check specifications:

```php
$response->isSuccess();  // Checks if transaction succeeded
$response->isRedirect(); // Checks if 3D Secure redirect needed
$response->isError();    // Checks if error occurred
```

---

## PSR Standards Compliance

### PSR-7: HTTP Message Interface

All requests implement `Psr\Http\Message\RequestInterface`:

```php
class AbstractRequest implements RequestInterface
{
    // PSR-7 methods
    public function getMethod(): string;
    public function getUri(): UriInterface;
    public function getHeaders(): array;
    public function getBody(): StreamInterface;
    // ... etc
}
```

All responses can be created from `Psr\Http\Message\ResponseInterface`:

```php
$response = Payment::fromHttpResponse($psrResponse);
```

### PSR-12: Extended Coding Style Guide

- Strict types declaration
- Return type declarations
- Typed properties
- Property/method visibility
- 4-space indentation

### PSR-4: Autoloading

Namespace structure matches directory structure:

```
Academe\Opayo\Pi\Request\CreatePayment
→ src/Request/CreatePayment.php
```

---

## Payment Flow Patterns

### Standard Payment Flow

```
1. CreateSessionKey
   ↓ (merchant session key)
2. JavaScript encrypts card on client
   ↓ (encrypted card data)
3. CreatePayment
   ↓
   ├─→ Payment (success) ─→ Done
   └─→ Secure3DRedirect ─→ 4. Redirect to bank
                              ↓
                           5. User completes 3DS
                              ↓
                           6. Bank redirects back
                              ↓
                           7. Parse Secure3DAcs
                              ↓
                           8. Send to Opayo
                              ↓
                           9. Payment (success) ─→ Done
```

### Deferred Payment Flow

```
1. CreateDeferred
   ↓
2. Deferred (authorized, not captured)
   ↓
   ├─→ CreateRelease (capture funds)
   └─→ CreateAbort (cancel authorization)
```

### Repeat Payment Flow

```
1. Save card identifier from first payment
   ↓
2. CreateRepeatPayment (with card identifier)
   ↓
3. Repeat (payment complete)
```

---

## Modernization Journey (PHP 8.1+)

### What Was Modernized

**From (Legacy):**
```php
<?php namespace Foo;

class Example
{
    const STATUS = 'active';
    protected $property;

    /**
     * @param string $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->property = $value;
        return $this;
    }
}
```

**To (Modern):**
```php
<?php

declare(strict_types=1);

namespace Foo;

class Example
{
    public const STATUS = 'active';

    public function __construct(
        protected readonly string $property
    ) {
    }

    public function withValue(string $value): static
    {
        $copy = clone $this;
        $copy->property = $value;
        return $copy;
    }
}
```

### Modernization Statistics

- **72 files** modernized
- **~350+ lines** of boilerplate removed
- **100%** strict types coverage
- **100%** typed properties
- **100%** return type declarations
- **100%** constant visibility
- **57 tests** passing throughout

---

## Security Considerations

### Sensitive Data Handling

```php
class SensitiveValue
{
    // Prevents accidental logging
    public function __toString(): string
    {
        return str_repeat('*', 6);
    }
}
```

### Authentication

```php
class Auth
{
    // Basic Auth credentials
    private string $integrationKey;
    private string $integrationPassword;

    // Used in Authorization header
}
```

### 3D Secure Support

Strong Customer Authentication (SCA) compliance:
- 3D Secure v1 (older)
- 3D Secure v2 (PSD2 compliant)

### Client-Side Card Encryption

Merchant session keys enable client-side encryption:
1. Fetch session key from Opayo
2. Use Opayo.js to encrypt card on client
3. Send encrypted data to server
4. Server never sees plain card data

---

## Testing Strategy

### Test Coverage

```bash
./vendor/bin/phpunit
```

- Unit tests for value objects
- Integration tests for request/response
- 57 tests, 102 assertions
- PHPUnit 10.5

### Test Organization

```
tests/
├── Money/               # Money value object tests
├── Request/             # Request builder tests
└── Response/            # Response parser tests
```

---

## Key Learnings & Skills

### 1. PHP 8.1+ Type System Mastery

- Strict types for safety
- Union types for flexibility
- Nullable types for optionals
- Mixed type for true polymorphism
- Static return type for inheritance
- Readonly properties for immutability

### 2. Object-Oriented Design

- SOLID principles
- Composition over inheritance
- Interface segregation
- Dependency injection
- Abstract base classes
- Template method pattern

### 3. Immutability & Functional Patterns

- Value objects
- Clone for immutability
- Fluent interfaces
- Pure functions
- No side effects in getters

### 4. PSR Standards

- PSR-7 HTTP messages
- PSR-12 coding style
- PSR-4 autoloading
- Interoperability focus

### 5. Payment Domain Knowledge

- 3D Secure authentication
- Strong Customer Authentication (SCA)
- Payment lifecycle (authorize, capture, refund)
- Tokenization for repeat payments
- Webhook handling
- PCI compliance patterns

### 6. API Client Design

- Request/response separation
- Factory pattern for flexibility
- Error handling strategies
- Static factory methods
- Type-safe builders

### 7. Code Quality

- Constructor validation
- Type safety
- Explicit over implicit
- Clear naming conventions
- Minimal documentation when types are clear
- Meaningful documentation for complexity

---

## Common Pitfalls & Solutions

### Pitfall 1: Forgetting Static Return Type

**Problem:**
```php
public function withAuth(Auth $auth): self  // ❌ Wrong!
{
    $clone = clone $this;
    return $clone->setAuth($auth);
}
```

**Solution:**
```php
public function withAuth(Auth $auth): static  // ✅ Correct!
{
    $clone = clone $this;
    return $clone->setAuth($auth);
}
```

**Why:** `static` enables proper inheritance, `self` refers to the class where defined.

### Pitfall 2: Mutable "with*" Methods

**Problem:**
```php
public function withAuth(Auth $auth): static
{
    $this->auth = $auth;  // ❌ Mutates original!
    return $this;
}
```

**Solution:**
```php
public function withAuth(Auth $auth): static
{
    $clone = clone $this;  // ✅ Create copy
    return $clone->setAuth($auth);
}
```

### Pitfall 3: Missing Strict Types

**Problem:**
```php
<?php
namespace Foo;  // ❌ No strict types!

class Example
{
    public function add($a, $b)  // Type coercion enabled
    {
        return $a + $b;
    }
}
```

**Solution:**
```php
<?php

declare(strict_types=1);  // ✅ Enable strict types

namespace Foo;

class Example
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
}
```

### Pitfall 4: Over-Documentation

**Problem:**
```php
/**
 * Get the amount
 * @return string The amount
 */
public function getAmount(): string  // ❌ Redundant docs
{
    return $this->amount;
}
```

**Solution:**
```php
public function getAmount(): string  // ✅ Types are self-documenting
{
    return $this->amount;
}
```

---

## Future Considerations

### Potential Improvements

1. **PHP 8.2+ Features**
   - `readonly` classes (entire class immutable)
   - Disjunctive Normal Form (DNF) types

2. **PHP 8.3+ Features**
   - Typed class constants
   - `json_validate()` for validation

3. **Architecture**
   - Consider event sourcing for transaction history
   - Add middleware pattern for request/response transformation
   - Implement repository pattern for transaction storage

4. **Testing**
   - Increase test coverage
   - Add integration tests with sandbox API
   - Add mutation testing

5. **Documentation**
   - Complete Mermaid sequence diagrams for all flows
   - Add more code examples
   - Create migration guide from v1 to v2

---

## Resources & References

### Opayo Documentation
- API Documentation: https://developer.opayo.com/
- 3D Secure Guide: https://developer.opayo.com/support/12/36/3d-secure
- Testing: https://test.sagepay.com/documentation/

### PHP Standards
- PSR-7: https://www.php-fig.org/psr/psr-7/
- PSR-12: https://www.php-fig.org/psr/psr-12/
- PHP 8.1: https://www.php.net/releases/8.1/

### Design Patterns
- Fluent Interface: https://martinfowler.com/bliki/FluentInterface.html
- Value Object: https://martinfowler.com/bliki/ValueObject.html
- Immutability: https://en.wikipedia.org/wiki/Immutable_object

---

**Document Version:** 1.0
**Last Updated:** 2025-11
**Maintainer:** Academe
