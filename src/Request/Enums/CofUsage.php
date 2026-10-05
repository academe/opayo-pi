<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Enums;

/**
 * Whether a stored credential is being saved for the first time or reused.
 * Enum equivalent of the CredentialType::COF_USAGE_* constants.
 */

enum CofUsage: string
{
    case First = 'First';
    case Subsequent = 'Subsequent';
}
