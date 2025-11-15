<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response;

/**
 * Transaction status values returned by Opayo.
 *
 * This enum defines all possible transaction status values.
 * The backed string values match the Opayo API specification.
 *
 * @example
 * ```php
 * // Check status using enum
 * if ($transaction->getStatusEnum() === TransactionStatus::OK) {
 *     // Transaction successful
 * }
 *
 * // Or use helper methods
 * if ($transaction->getStatusEnum()?->isSuccess()) {
 *     // Transaction successful
 * }
 *
 * // Backwards compatible - constants still work
 * if ($transaction->getStatusString() === AbstractTransaction::STATUS_OK) {
 *     // Transaction successful
 * }
 * ```
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
     *
     * This provides more flexibility than the built-in tryFrom() by handling
     * different capitalizations that might come from various sources.
     *
     * @param string|null $value The status string from API or storage
     * @return self|null The enum case, or null if value doesn't match
     */
    public static function tryFromInsensitive(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        // Try exact match first (most common case, fastest path)
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
     *
     * @return bool True if status is OK
     */
    public function isSuccess(): bool
    {
        return $this === self::OK;
    }

    /**
     * Check if this status represents an authentication requirement.
     *
     * @return bool True if 3D Secure authentication is required
     */
    public function requiresAuthentication(): bool
    {
        return $this === self::THREE_D_AUTH;
    }

    /**
     * Check if this status represents an error state.
     *
     * @return bool True if status indicates transaction failure or error
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
     * Check if this status requires user action or represents a final state.
     *
     * @return bool True if status is final (not pending authentication)
     */
    public function isFinal(): bool
    {
        return $this !== self::THREE_D_AUTH;
    }

    /**
     * Get human-readable description of the status.
     *
     * Useful for logging, debugging, or admin interfaces.
     * Do not show to end users - use statusDetail from API instead.
     *
     * @return string Human-readable status description
     */
    public function description(): string
    {
        return match ($this) {
            self::OK => 'Transaction successful',
            self::NOT_AUTHED => 'Transaction not authenticated',
            self::REJECTED => 'Transaction rejected by bank',
            self::THREE_D_AUTH => '3D Secure authentication required',
            self::MALFORMED => 'Malformed request',
            self::INVALID => 'Invalid request',
            self::ERROR => 'Transaction error',
        };
    }

    /**
     * Get the severity level of this status.
     *
     * Useful for logging or alert systems.
     *
     * @return string One of: 'success', 'info', 'warning', 'error'
     */
    public function severity(): string
    {
        return match ($this) {
            self::OK => 'success',
            self::THREE_D_AUTH => 'info',
            self::NOT_AUTHED, self::REJECTED => 'warning',
            self::MALFORMED, self::INVALID, self::ERROR => 'error',
        };
    }
}
