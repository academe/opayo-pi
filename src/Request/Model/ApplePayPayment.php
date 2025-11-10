<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Model;

/**
 * Apple Pay payment method for transactions.
 *
 * Apple Pay tokens are obtained from the Apple Pay JS API in the browser.
 * The token must be Base64 encoded before being sent to Opayo.
 *
 * Two integration types are supported:
 * - Opayo-managed certificate: Requires sessionValidationToken
 * - Merchant-managed certificate: No sessionValidationToken needed
 */

use Academe\Opayo\Pi\Helper;

class ApplePayPayment implements PaymentMethodInterface
{
    public function __construct(
        protected string $clientIpAddress,
        protected string $payload,
        protected ?string $sessionValidationToken = null
    ) {
    }

    /**
     * Construct an instance from stored data (e.g. JSON serialized object).
     */
    public static function fromData(array|object|string $data): static
    {
        // For convenience
        if (is_string($data)) {
            $data = json_decode($data);
        }

        // The data will normally be in an "applePay" wrapper element
        if ($applePay = Helper::dataGet($data, 'applePay')) {
            $data = $applePay;
        }

        return new static(
            Helper::dataGet($data, 'clientIpAddress'),
            Helper::dataGet($data, 'payload'),
            Helper::dataGet($data, 'sessionValidationToken')
        );
    }

    /**
     * Return the complete object data for serialized storage and API submission.
     */
    public function jsonSerialize(): mixed
    {
        $message = [
            'applePay' => [
                'clientIpAddress' => $this->clientIpAddress,
                'payload' => $this->payload,
            ],
        ];

        // Only include sessionValidationToken for Opayo-managed certificate integration
        if ($this->sessionValidationToken !== null) {
            $message['applePay']['sessionValidationToken'] = $this->sessionValidationToken;
        }

        return $message;
    }

    public function getClientIpAddress(): string
    {
        return $this->clientIpAddress;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function getSessionValidationToken(): ?string
    {
        return $this->sessionValidationToken;
    }

    /**
     * Create a new instance with a different session validation token.
     * Useful for Opayo-managed certificate integration.
     */
    public function withSessionValidationToken(string $sessionValidationToken): static
    {
        $clone = clone $this;
        $clone->sessionValidationToken = $sessionValidationToken;
        return $clone;
    }
}
