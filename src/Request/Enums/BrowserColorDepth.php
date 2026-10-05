<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Enums;

/**
 * The colour depth of the cardholder's browser, in bits per pixel, as
 * reported for 3D Secure v2 device profiling.
 * Enum equivalent of the StrongCustomerAuthentication::BROWSER_COLOR_DEPTH_*
 * constants.
 */

enum BrowserColorDepth: int
{
    case Depth1 = 1;
    case Depth4 = 4;
    case Depth8 = 8;
    case Depth15 = 15;
    case Depth16 = 16;
    case Depth24 = 24;
    case Depth32 = 32;
    case Depth48 = 48;
}
