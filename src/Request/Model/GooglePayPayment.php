<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Model;

use Academe\Opayo\Pi\Helper;

/**
 * Google Pay payment method for transactions (paymentMethod.googlePay).
 *
 * Wire format (Opayo Pi API reference, paymentMethodObjects/googlePay):
 *
 *   merchantSessionKey  required  the merchant session key used to initiate the transaction
 *   clientIpAddress     required  the shopper's IPv4/IPv6 address
 *   payload             required  paymentData.paymentMethodData.tokenizationData.token from
 *                                 the Google Pay API, base64 encoded
 *
 * The Google Pay JS tokenizationSpecification must use gateway "opayoelavon"
 * and the gatewayMerchantId shown in MyOpayo (Settings > Pay Methods > Google Pay).
 */

class GooglePayPayment implements PaymentMethodInterface
{
    public function __construct(
        protected string $merchantSessionKey,
        protected string $clientIpAddress,
        protected string $payload
    ) {
    }

    /**
     * Build from the raw token string returned by the Google Pay API
     * (paymentData.paymentMethodData.tokenizationData.token), base64-encoding it.
     */
    public static function fromGoogleToken(string $merchantSessionKey, string $clientIpAddress, string $token): static
    {
        return new static($merchantSessionKey, $clientIpAddress, base64_encode($token));
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
            (string)Helper::dataGet($data, 'merchantSessionKey'),
            (string)Helper::dataGet($data, 'clientIpAddress'),
            (string)Helper::dataGet($data, 'payload')
        );
    }

    /**
     * Return the complete object data for serialized storage and API submission.
     */
    public function jsonSerialize(): mixed
    {
        return [
            'googlePay' => [
                'merchantSessionKey' => $this->merchantSessionKey,
                'clientIpAddress' => $this->clientIpAddress,
                'payload' => $this->payload,
            ],
        ];
    }

    public function getMerchantSessionKey(): string
    {
        return $this->merchantSessionKey;
    }

    public function getClientIpAddress(): string
    {
        return $this->clientIpAddress;
    }

    /**
     * The base64-encoded Google Pay token.
     */
    public function getPayload(): string
    {
        return $this->payload;
    }
}
