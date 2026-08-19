<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Enums;

/**
 * Whether AVS/CVC checks are applied to a transaction.
 * Enum equivalent of the CreatePayment::APPLY_AVS_CVC_CHECK_* constants.
 */

enum ApplyAvsCvcCheck: string
{
    case UseMSPSetting = 'UseMSPSetting';
    case Force = 'Force';
    case Disable = 'Disable';
    case ForceIgnoringRules = 'ForceIgnoringRules';
}
