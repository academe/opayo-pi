<?php

/**
 * Account switcher: run the whole demo against your .env account or Elavon's
 * public sandbox profile (which has the 3D Secure simulation and PayPal
 * enabled). Overrides the .env credentials before bootstrap.php builds Auth.
 */

declare(strict_types=1);

/** Published by Elavon on developer.elavon.com ("Test in Sandbox"). */
const DEBUG_PUBLIC_SANDBOX = [
    'OPAYO_VENDOR_NAME' => 'sandbox',
    'OPAYO_INTEGRATION_KEY' => 'hJYxsw7HLbj40cB8udES8CDRFLhuJ8G54O6rDpUXvE6hYDrria',
    'OPAYO_INTEGRATION_PASSWORD' => 'o2iHSrFybYMZpmWOQMuhsXP52V4fBtpuSDshrKDSWsBY1OiN6hwd9Kb12z4j5Us5u',
    'OPAYO_ENVIRONMENT' => 'test',
];

function debugApplyAccount(): void
{
    if (isset($_GET['debugAccount'])) {
        $_SESSION['debugAccount'] = $_GET['debugAccount'] === 'sandbox' ? 'sandbox' : 'env';
    }

    if (($_SESSION['debugAccount'] ?? 'env') === 'sandbox') {
        // Left-hand keys win, so the sandbox values replace the .env ones.
        $_ENV = DEBUG_PUBLIC_SANDBOX + $_ENV;
    }
}
