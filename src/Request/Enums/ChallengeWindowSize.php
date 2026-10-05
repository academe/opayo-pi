<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Enums;

/**
 * Size of the 3D Secure v2 challenge window presented to the cardholder.
 * Enum equivalent of the StrongCustomerAuthentication::CHALLENGE_WINDOW_SIZE_*
 * constants.
 */

enum ChallengeWindowSize: string
{
    case Small = 'Small';
    case Medium = 'Medium';
    case Large = 'Large';
    case ExtraLarge = 'ExtraLarge';
    case FullScreen = 'FullScreen';
}
