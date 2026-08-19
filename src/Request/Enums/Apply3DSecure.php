<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Enums;

/**
 * Whether 3D Secure authentication is applied to a transaction.
 * Enum equivalent of the CreatePayment::APPLY_3D_SECURE_* constants.
 * The deprecated ForceIgnoringRules value (removed from the API spec
 * 2023-10-26) is intentionally not available here.
 */

enum Apply3DSecure: string
{
    case UseMSPSetting = 'UseMSPSetting';
    case Force = 'Force';
    case Disable = 'Disable';
}
