# Enum Migration Guide

This guide demonstrates how to introduce PHP 8.1+ enums while maintaining backwards compatibility with existing constants.

## Table of Contents

1. [Pattern Overview](#pattern-overview)
2. [Complete Example: Transaction Status](#complete-example-transaction-status)
3. [Implementation Checklist](#implementation-checklist)
4. [Additional Examples](#additional-examples)
5. [Benefits](#benefits)

---

## Pattern Overview

The goal is to:
- ✅ Introduce modern PHP 8.1+ backed enums for type safety
- ✅ Maintain 100% backwards compatibility with existing constants
- ✅ Provide a smooth migration path for library consumers
- ✅ Make constants reference enums (single source of truth)

### Key Principles

1. **Enums as Source of Truth**: Enum cases define the actual values
2. **Constants for BC**: Class constants use string literals (PHP 8.1) or enum values (PHP 8.2+)
3. **Flexible Type Hints**: Accept both enum and string in methods
4. **Smart Conversion**: Helper methods convert between enum/string seamlessly
5. **Internal Enum Usage**: Internally prefer enums, externally support both

### PHP 8.1 vs 8.2 Difference

**Important:** PHP 8.1 does not allow property access (`->value`) in constant expressions.

```php
// ❌ PHP 8.1: Does NOT work
public const STATUS_OK = TransactionStatus::OK->value;

// ✅ PHP 8.1: Use string literal
public const STATUS_OK = 'Ok';

// ✅ PHP 8.2+: Can use enum->value
public const STATUS_OK = TransactionStatus::OK->value;
```

**This guide uses PHP 8.1 compatible patterns** (string literals) to maximize compatibility.

---

## Complete Example: Transaction Status

### Step 1: Create the Enum

Create a new file: `src/Response/TransactionStatus.php`

```php
<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response;

/**
 * Transaction status values returned by Opayo.
 *
 * This enum defines all possible transaction status values.
 * The backed string values match the Opayo API specification.
 */
enum TransactionStatus: string
{
    case OK = 'Ok';
    case NOT_AUTHED = 'NotAuthed';
    case REJECTED = 'Rejected';
    case THREE_D_AUTH = '3DAuth';
    case MALFORMED = 'Malformed';
    case INVALID = 'Invalid';
    case ERROR = 'Error';

    /**
     * Create enum from string value (case-insensitive).
     * Returns null if value doesn't match any case.
     */
    public static function tryFromInsensitive(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        // Try exact match first (most common case)
        $case = self::tryFrom($value);
        if ($case !== null) {
            return $case;
        }

        // Try case-insensitive match
        $upperValue = strtoupper($value);
        foreach (self::cases() as $case) {
            if (strtoupper($case->value) === $upperValue) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Check if this status represents a successful transaction.
     */
    public function isSuccess(): bool
    {
        return $this === self::OK;
    }

    /**
     * Check if this status represents an authentication requirement.
     */
    public function requiresAuthentication(): bool
    {
        return $this === self::THREE_D_AUTH;
    }

    /**
     * Check if this status represents an error.
     */
    public function isError(): bool
    {
        return in_array($this, [
            self::NOT_AUTHED,
            self::REJECTED,
            self::MALFORMED,
            self::INVALID,
            self::ERROR,
        ], true);
    }

    /**
     * Get human-readable description of the status.
     */
    public function description(): string
    {
        return match ($this) {
            self::OK => 'Transaction successful',
            self::NOT_AUTHED => 'Transaction not authenticated',
            self::REJECTED => 'Transaction rejected',
            self::THREE_D_AUTH => '3D Secure authentication required',
            self::MALFORMED => 'Malformed request',
            self::INVALID => 'Invalid request',
            self::ERROR => 'Transaction error',
        };
    }
}
```

### Step 2: Update the Class to Reference the Enum

Update `src/Response/AbstractTransaction.php`:

```php
<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response;

use Academe\Opayo\Pi\Money\CurrencyInterface;
use Academe\Opayo\Pi\Money\Currency;
use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Money\AmountInterface;
use Academe\Opayo\Pi\Helper;

abstract class AbstractTransaction extends AbstractResponse
{
    /**
     * Transaction status constants (for backwards compatibility).
     *
     * @deprecated Use TransactionStatus enum instead. These constants will remain
     *             for backwards compatibility but new code should use the enum.
     *
     * Note: PHP 8.1 compatible - uses string literals (not enum->value).
     *       Tests verify these match the enum values.
     */
    public const STATUS_OK = 'Ok';
    public const STATUS_NOTAUTHED = 'NotAuthed';
    public const STATUS_REJECTED = 'Rejected';
    public const STATUS_3DAUTH = '3DAuth';
    public const STATUS_MALFORMED = 'Malformed';
    public const STATUS_INVALID = 'Invalid';
    public const STATUS_ERROR = 'Error';

    /**
     * The status, statusCode and statusReason are used in all transaction responses.
     * Internally stored as enum, but accessible as string for BC.
     */
    protected ?TransactionStatus $statusEnum = null;
    protected ?string $statusCode = null;
    protected ?string $statusDetail = null;

    // ... rest of properties ...

    /**
     * Set the three status fields from body data.
     */
    protected function setStatuses(mixed $data): void
    {
        $statusValue = Helper::dataGet($data, 'status', null);

        // Store as enum internally
        if ($statusValue !== null) {
            $this->statusEnum = TransactionStatus::tryFromInsensitive($statusValue);
        }

        $this->statusCode = Helper::dataGet($data, 'statusCode', null);
        $this->statusDetail = Helper::dataGet($data, 'statusDetail', null);
    }

    /**
     * Get the transaction status.
     *
     * @return TransactionStatus|string|null Returns TransactionStatus enum when possible,
     *                                       string for unknown values, null if not set.
     */
    public function getStatus(): TransactionStatus|string|null
    {
        return $this->statusEnum ?? $this->constantValue('STATUS', $this->status ?? '');
    }

    /**
     * Get the transaction status as enum (preferred for new code).
     *
     * @return TransactionStatus|null Returns enum or null if status is unknown/not set.
     */
    public function getStatusEnum(): ?TransactionStatus
    {
        return $this->statusEnum;
    }

    /**
     * Get the transaction status as string (for backwards compatibility).
     *
     * @return string|null
     */
    public function getStatusString(): ?string
    {
        return $this->statusEnum?->value;
    }

    /**
     * Check if the transaction was successful.
     * Convenience method that works with both enum and string.
     */
    public function isSuccessful(): bool
    {
        return $this->statusEnum?->isSuccess() ?? false;
    }

    /**
     * Check if the transaction requires 3D Secure authentication.
     */
    public function requires3DSecure(): bool
    {
        return $this->statusEnum?->requiresAuthentication() ?? false;
    }

    // ... rest of methods ...
}
```

### Step 3: Update AbstractResponse (if needed)

The `getStatus()` method in AbstractResponse already handles string values via `constantValue()`. For enum support, you could update it:

```php
/**
 * Get the status - returns enum when possible, string otherwise.
 *
 * @return mixed TransactionStatus enum, string, or null
 */
public function getStatus(): mixed
{
    // If child class uses enum (like AbstractTransaction)
    if ($this->statusEnum ?? null) {
        return $this->statusEnum;
    }

    // Fallback to string value with capitalization correction
    $statusValue = $this->constantValue('STATUS', $this->status ?? '');
    return ! empty($statusValue) ? $statusValue : $this->status;
}
```

---

## Implementation Checklist

When adding enums to an existing constant-based system:

### 1. Create the Enum
- [ ] Create new enum file in appropriate namespace
- [ ] Use `enum Name: string` for string-backed enum
- [ ] Define all cases with exact API values
- [ ] Add `tryFromInsensitive()` helper for flexible parsing
- [ ] Add domain-specific helper methods (isSuccess, isError, etc.)
- [ ] Add `description()` method for human-readable text

### 2. Update the Class
- [ ] Add `@deprecated` doc to existing constants
- [ ] **For PHP 8.1:** Keep constants as string literals (cannot use `Enum::CASE->value`)
- [ ] **For PHP 8.2+:** Optionally reference enum values (`Enum::CASE->value`)
- [ ] Add new typed property for enum (`protected ?EnumType $propertyEnum = null`)
- [ ] Keep old property for BC if needed, or convert it
- [ ] Update setter to parse into enum
- [ ] Add dedicated `getXxxEnum(): ?EnumType` method (returns enum)
- [ ] Keep original getter returning string for BC
- [ ] Add convenience boolean methods using enum logic

### 3. Maintain Backwards Compatibility
- [ ] Constants still work (string literals matching enum values)
- [ ] String comparisons still work (`$status === 'Ok'`)
- [ ] String type hints still work
- [ ] Existing code doesn't break

### 4. PHP Version Considerations

**PHP 8.1 Limitation:**
```php
// ❌ Does NOT work in PHP 8.1 - property access in constant expressions
public const STATUS_OK = TransactionStatus::OK->value;

// ✅ Works in PHP 8.1 - string literal
public const STATUS_OK = 'Ok';
```

**PHP 8.2+ Enhancement:**
```php
// ✅ Works in PHP 8.2+ - enum property access allowed in constants
public const STATUS_OK = TransactionStatus::OK->value;
```

**Recommendation:** Use string literals for PHP 8.1 compatibility. The values
must match the enum values exactly (verified by tests).

### 4. Update Tests
- [ ] Test enum cases exist
- [ ] Test constant values match enum values
- [ ] Test string conversion
- [ ] Test case-insensitive parsing
- [ ] Test helper methods (isSuccess, etc.)
- [ ] Test backwards compatibility (constants, strings)

### 5. Update Documentation
- [ ] Add enum usage examples to README
- [ ] Update CODING-PATTERNS.md with enum pattern
- [ ] Note deprecation of constants (but continued support)
- [ ] Show migration path for consumers

---

## Additional Examples

### Entry Method Enum

```php
<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

enum EntryMethod: string
{
    case ECOMMERCE = 'Ecommerce';
    case MAIL_ORDER = 'MailOrder';
    case TELEPHONE_ORDER = 'TelephoneOrder';

    public function isRemote(): bool
    {
        return $this !== self::ECOMMERCE;
    }
}
```

Usage in `CreatePayment.php`:

```php
// Constants for BC (PHP 8.1 compatible - string literals)
/**
 * @deprecated Use EntryMethod enum instead
 */
public const ENTRY_METHOD_ECOMMERCE = 'Ecommerce';
public const ENTRY_METHOD_MAILORDER = 'MailOrder';
public const ENTRY_METHOD_TELEPHONEORDER = 'TelephoneOrder';

// Property - store as enum internally
protected ?EntryMethod $entryMethodEnum = null;

// Setter - accepts enum or string
public function setEntryMethod(EntryMethod|string|null $value): void
{
    $this->entryMethodEnum = is_string($value)
        ? EntryMethod::tryFrom($value)
        : $value;
}

// Getter returning enum (preferred for new code)
public function getEntryMethodEnum(): ?EntryMethod
{
    return $this->entryMethodEnum;
}

// Getter returning string (backwards compatibility)
public function getEntryMethod(): ?string
{
    return $this->entryMethodEnum?->value;
}
```

### Challenge Window Size Enum

```php
<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Model;

enum ChallengeWindowSize: string
{
    case SMALL = 'Small';
    case MEDIUM = 'Medium';
    case LARGE = 'Large';
    case EXTRA_LARGE = 'ExtraLarge';
    case FULL_SCREEN = 'FullScreen';

    /**
     * Get recommended size based on device type.
     */
    public static function forDevice(string $deviceType): self
    {
        return match ($deviceType) {
            'mobile' => self::SMALL,
            'tablet' => self::MEDIUM,
            'desktop' => self::LARGE,
            default => self::MEDIUM,
        };
    }

    /**
     * Get pixel dimensions (width x height).
     */
    public function dimensions(): array
    {
        return match ($this) {
            self::SMALL => [250, 400],
            self::MEDIUM => [390, 400],
            self::LARGE => [500, 600],
            self::EXTRA_LARGE => [600, 400],
            self::FULL_SCREEN => [0, 0], // Full screen
        };
    }
}
```

---

## Benefits

### For Library Maintainers

1. **Type Safety**: IDEs and static analyzers can verify enum usage
2. **Autocomplete**: IDEs suggest valid enum cases
3. **Refactoring**: Rename/change detection across codebase
4. **Documentation**: Enums self-document valid values
5. **Domain Logic**: Helper methods encapsulate business rules
6. **Single Source**: Enum defines values, constants reference them

### For Library Consumers

1. **Backwards Compatible**: Existing code continues to work
2. **Gradual Migration**: Adopt enums at your own pace
3. **Better DX**: Modern code gets type hints and autocomplete
4. **Less Error-Prone**: Can't typo an enum case
5. **Discoverability**: IDE shows all valid options

### Migration Path for Consumers

```php
// OLD: Using string constants (still works - getStatus() returns string)
if ($transaction->getStatus() === AbstractTransaction::STATUS_OK) {
    // Handle success
}

// OLD: Direct string comparison (still works)
if ($transaction->getStatus() === 'Ok') {
    // Handle success
}

// NEW: Using enum directly (recommended - use getStatusEnum())
if ($transaction->getStatusEnum() === TransactionStatus::OK) {
    // Handle success
}

// BEST: Using convenience helper methods
if ($transaction->isSuccessful()) {
    // Handle success - most readable!
}
```

**Key API Methods:**
- `getStatus()` - Returns `?string` (backwards compatible)
- `getStatusEnum()` - Returns `?TransactionStatus` (new, type-safe)
- `isSuccessful()` - Returns `bool` (convenience helper)
- `requires3DSecure()` - Returns `bool` (convenience helper)
- `hasError()` - Returns `bool` (convenience helper)

---

## Recommended Candidates for Enum Migration

Based on current codebase analysis:

### High Priority
1. ✅ **TransactionStatus** (AbstractTransaction) - 7 values, heavily used
2. ✅ **EntryMethod** (CreatePayment) - 3 values, clear domain concept
3. ✅ **TransactionType** (AbstractRequest) - 4 values, core concept
4. ✅ **InstructionType** (AbstractRequest) - 3 values, clear set

### Medium Priority
5. **AvsCvcCheckStatus** (AvsCvcCheck) - 5 values
6. **AvsCvcCheckResult** (AvsCvcCheck) - 4 values
7. **ChallengeWindowSize** (StrongCustomerAuthentication) - 5 values
8. **CofUsage** (CredentialType) - 2 values
9. **InitiatedType** (CredentialType) - 2 values
10. **MitType** (CredentialType) - 8 values

### Lower Priority
- **ApplyAvsCvcCheck** (CreatePayment) - 4 values, but might need business logic
- **TransType** (StrongCustomerAuthentication) - 5 values

---

## Best Practices

1. **Always use backed enums** (`enum Name: string`) for API values
2. **Provide helper methods** for common operations (isSuccess, isError, etc.)
3. **Use `tryFrom()` not `from()`** to handle invalid values gracefully
4. **Add case-insensitive parsing** for flexibility
5. **Keep constants** for backwards compatibility
6. **Use union types** (`Enum|string|null`) in getters for flexibility
7. **Document migration path** in docblocks
8. **Add match expressions** for enum-specific behavior
9. **Test thoroughly** - both enum and string paths

---

**Last Updated:** 2025-11
**PHP Version:** 8.1+
**Status:** Recommended Pattern
