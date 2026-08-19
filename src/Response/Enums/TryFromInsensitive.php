<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response\Enums;

/**
 * Case-insensitive parsing for string-backed response enums.
 * Gateway responses should match the documented casing, but this guards
 * against variations from storage or older API versions. An unrecognised
 * value returns null rather than throwing, so new values the gateway may
 * add do not break response parsing.
 */

trait TryFromInsensitive
{
    /**
     * @param string|null $value The value from the API or storage
     * @return static|null The enum case, or null if the value doesn't match
     */
    public static function tryFromInsensitive(?string $value): ?static
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
}
