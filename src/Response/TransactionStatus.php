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
    use Enums\TryFromInsensitive;

    case OK = 'Ok';
    case NOT_AUTHED = 'NotAuthed';
    case REJECTED = 'Rejected';
    case THREE_D_AUTH = '3DAuth';
    case MALFORMED = 'Malformed';
    case INVALID = 'Invalid';
    case ERROR = 'Error';

    /**
     * Returned only when the transactionType is Authenticate:
     * the 3D Secure checks failed or were not performed, but the card
     * details are still secured at Opayo (no liability shift).
     */
    case REGISTERED = 'Registered';

    /**
     * Returned only when the transactionType is Authenticate:
     * the 3D Secure checks were performed successfully and the card
     * details secured at Opayo.
     */
    case AUTHENTICATED = 'Authenticated';

    /**
     * Check if this status represents a successful transaction.
     *
     * @return bool True if status is OK
     */
    public function isSuccess(): bool
    {
        // Registered and Authenticated are successful Authenticate outcomes:
        // in both cases the card details were secured at Opayo. A Registered
        // authentication carries no 3D Secure liability shift, which is
        // reflected in its severity() of 'warning'.
        return in_array($this, [
            self::OK,
            self::REGISTERED,
            self::AUTHENTICATED,
        ], true);
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
            self::REGISTERED => 'Card details secured; 3D Secure failed or not performed',
            self::AUTHENTICATED => '3D Secure authenticated and card details secured',
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
            self::OK, self::AUTHENTICATED => 'success',
            self::THREE_D_AUTH => 'info',
            self::NOT_AUTHED, self::REJECTED, self::REGISTERED => 'warning',
            self::MALFORMED, self::INVALID, self::ERROR => 'error',
        };
    }
}
