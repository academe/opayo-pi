<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Checkout;

use Academe\Opayo\Pi\Request\Enums\ChallengeWindowSize;
use Academe\Opayo\Pi\Request\Enums\TransType;
use Academe\Opayo\Pi\Request\Model\StrongCustomerAuthentication;

/**
 * What the shopper's browser reports for 3D Secure v2.
 *
 * The browser collects these values (see resources/js/browser-data.js) and
 * posts them with the payment form. Build this from the posted fields, then
 * turn it into the strongCustomerAuthentication object CreatePayment needs.
 * Send it for every payment method: a wallet token can be challenged too.
 */
final class BrowserData
{
    /** The form field names this class reads, and the snippet fills. */
    public const FIELDS = [
        'browserLanguage',
        'browserColorDepth',
        'browserScreenHeight',
        'browserScreenWidth',
        'browserTz',
    ];

    /** Colour depths Opayo accepts; anything else is reported as 24. */
    private const COLOR_DEPTHS = [1, 4, 8, 15, 16, 24, 32, 48];

    public function __construct(
        public readonly string $language = 'en-GB',
        public readonly int $colorDepth = 24,
        public readonly int $screenHeight = 0,
        public readonly int $screenWidth = 0,
        public readonly int $timezoneOffset = 0,
    ) {
    }

    /**
     * @param array<string, mixed> $fields Usually the posted form fields.
     */
    public static function fromArray(array $fields): self
    {
        $int = static fn (string $name, int $default): int =>
            isset($fields[$name]) && is_numeric($fields[$name]) ? (int) $fields[$name] : $default;

        $depth = $int('browserColorDepth', 24);
        $language = trim((string) ($fields['browserLanguage'] ?? ''));

        return new self(
            language: $language !== '' ? $language : 'en-GB',
            colorDepth: in_array($depth, self::COLOR_DEPTHS, true) ? $depth : 24,
            screenHeight: $int('browserScreenHeight', 0),
            screenWidth: $int('browserScreenWidth', 0),
            timezoneOffset: $int('browserTz', 0),
        );
    }

    /**
     * @param string $notificationUrl Where the shopper's browser returns after a challenge.
     * @param string $clientIp        The shopper's IP; Opayo accepts IPv4 only, so anything
     *                                else is sent as 127.0.0.1.
     */
    public function toStrongCustomerAuthentication(
        string $notificationUrl,
        string $clientIp,
        string $acceptHeader,
        string $userAgent
    ): StrongCustomerAuthentication {
        return new StrongCustomerAuthentication(
            $notificationUrl,
            filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ? $clientIp : '127.0.0.1',
            $acceptHeader !== '' ? $acceptHeader : '*/*',
            true,
            $this->language,
            $userAgent !== '' ? $userAgent : 'Unknown',
            ChallengeWindowSize::Medium,
            TransType::GoodsAndServicePurchase,
            [
                'browserJavaEnabled' => false,
                'browserColorDepth' => $this->colorDepth,
                'browserScreenHeight' => $this->screenHeight,
                'browserScreenWidth' => $this->screenWidth,
                'browserTz' => $this->timezoneOffset,
            ]
        );
    }
}
