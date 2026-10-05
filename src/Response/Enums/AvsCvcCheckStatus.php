<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response\Enums;

/**
 * The overall result of the AVS/CVC checks in a transaction response.
 * Enum equivalent of the AvsCvcCheck::AVSCVCCHECK_STATUS_* constants.
 */

enum AvsCvcCheckStatus: string
{
    use TryFromInsensitive;

    case ALL_MATCHED = 'AllMatched';
    case SECURITY_CODE_MATCH_ONLY = 'SecurityCodeMatchOnly';
    case ADDRESS_MATCH_ONLY = 'AddressMatchOnly';
    case NO_MATCHES = 'NoMatches';
    case NOT_CHECKED = 'NotChecked';
}
