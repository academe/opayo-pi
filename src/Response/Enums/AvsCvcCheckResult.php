<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response\Enums;

/**
 * The result of an individual AVS/CVC check (address, postal code or
 * security code) in a transaction response.
 * Enum equivalent of the AvsCvcCheck::AVSCVCCHECK_RESULT_* constants.
 */

enum AvsCvcCheckResult: string
{
    use TryFromInsensitive;

    case MATCHED = 'Matched';
    case NOT_PROVIDED = 'NotProvided';
    case NOT_CHECKED = 'NotChecked';
    case NOT_MATCHED = 'NotMatched';
}
