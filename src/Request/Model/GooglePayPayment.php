<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Model;

use Academe\Opayo\Pi\Helper;

/**
 * Google Pay payment method for transactions.
 *
 * Google Pay tokens are obtained from the Google Pay API in the browser.
 * The token must be Base64 encoded before being sent to Opayo.
 */

class GooglePayPayment implements PaymentMethodInterface
{
    public function __construct(
        protected string $clientIpAddress,
        protected string $payload
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

        // The data will normally be in a "googlePay" wrapper element
        if ($googlePay = Helper::dataGet($data, 'googlePay')) {
            $data = $googlePay;
        }

        return new static(
            Helper::dataGet($data, 'clientIpAddress'),
            Helper::dataGet($data, 'payload')
        );
    }

    /**
     * Return the complete object data for serialized storage and API submission.
     */
    public function jsonSerialize(): mixed
    {
        return [
            'googlePay' => [
                'clientIpAddress' => $this->clientIpAddress,
                'payload' => $this->payload,
            ],
        ];
    }

    public function getClientIpAddress(): string
    {
        return $this->clientIpAddress;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }
}
