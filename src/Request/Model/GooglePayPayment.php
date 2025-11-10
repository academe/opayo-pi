<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Model;

/**
 * Google Pay payment method for transactions.
 *
 * Google Pay tokens are obtained from the Google Pay API in the browser.
 * The token must be Base64 encoded before being sent to Opayo.
 */

use Academe\Opayo\Pi\Helper;

class GooglePayPayment implements PaymentMethodInterface
{
    /**
     * The client's IP address (IPv4 or IPv6).
     */
    protected string $clientIpAddress;

    /**
     * The Base64-encoded Google Pay payment token from Google Pay API.
     */
    protected string $payload;

    /**
     * @param string $clientIpAddress The customer's IP address
     * @param string $payload Base64-encoded Google Pay token from Google
     */
    public function __construct(
        string $clientIpAddress,
        string $payload
    ) {
        $this->clientIpAddress = $clientIpAddress;
        $this->payload = $payload;
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
