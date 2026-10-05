<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Enums;

/**
 * The reason a stored credential is used for a Merchant Initiated Transaction.
 * Enum equivalent of the CredentialType::MIT_TYPE_* constants.
 * Check with the acquirer which MIT types they support; Unscheduled and
 * Recurring are typically supported by all.
 */

enum MitType: string
{
    case Recurring = 'Recurring';
    case Instalment = 'Instalment';
    case Unscheduled = 'Unscheduled';
    case Incremental = 'Incremental';
    case DelayedCharge = 'DelayedCharge';
    case NoShow = 'NoShow';
    case Reauthorisation = 'Reauthorisation';
    case Resubmission = 'Resubmission';
}
