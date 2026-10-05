<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Enums;

/**
 * Whether the cardholder is in-session (CIT) or the merchant initiates the
 * transaction off-session (MIT).
 * Enum equivalent of the CredentialType::INITIATED_TYPE_* constants.
 */

enum InitiatedType: string
{
    case ConsumerInitiated = 'CIT';
    case MerchantInitiated = 'MIT';
}
