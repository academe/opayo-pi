<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Model;

use Academe\Opayo\Pi\Helper;
use InvalidArgumentException;

/**
 * Apple Pay payment method for transactions (paymentMethod.applePay).
 *
 * Wire format (Opayo Pi API reference, paymentMethodObjects/applePay):
 *
 *   merchantSessionKey      required  the merchant session key used to initiate the transaction
 *   clientIpAddress         required  the shopper's IPv4/IPv6 address
 *   paymentData             required  Apple's "paymentData" object, base64 encoded, INCLUDING
 *                                     the top-level paymentData node
 *   sessionValidationToken  required when Opayo manages the certificate: the token returned
 *                                     by CreateApplePaySession (POST /applepay/sessions); omit
 *                                     for a merchant-managed certificate
 *   applicationData         optional  from paymentData.header.applicationData
 *   displayName             optional  from paymentMethod.displayName, e.g. "Visa 1234"
 *   paymentMethodType       optional  from paymentMethod.type, e.g. "debit"
 *
 * The Apple Pay token comes from the Apple Pay JS API (session.onpaymentauthorized,
 * event.payment.token) in Safari on an Apple device; it cannot be fabricated
 * server-side, so this class only transports it.
 */

class ApplePayPayment implements PaymentMethodInterface
{
    public function __construct(
        protected string $merchantSessionKey,
        protected string $clientIpAddress,
        protected string $paymentData,
        protected ?string $sessionValidationToken = null,
        protected ?string $applicationData = null,
        protected ?string $displayName = null,
        protected ?string $paymentMethodType = null
    ) {
    }

    /**
     * Build from Apple's raw payment token, as delivered to the browser:
     * the object containing paymentData, paymentMethod and transactionIdentifier.
     * Base64-encodes the paymentData node and picks out the optional fields.
     *
     * @param string $merchantSessionKey
     * @param string $clientIpAddress
     * @param array|object|string $token Apple's payment.token (array, object or JSON)
     * @param string|null $sessionValidationToken From CreateApplePaySession (Opayo-managed certificate)
     */
    public static function fromAppleToken(
        string $merchantSessionKey,
        string $clientIpAddress,
        array|object|string $token,
        ?string $sessionValidationToken = null
    ): static {
        if (is_string($token)) {
            $token = json_decode($token);
        }

        if (! is_array($token) && ! is_object($token)) {
            throw new InvalidArgumentException(
                'Apple Pay token must be an array, an object, or a JSON string encoding one.'
            );
        }

        // Accept either the whole payment object or its "token" member.
        if (Helper::dataGet($token, 'token')) {
            $token = Helper::dataGet($token, 'token');
        }

        $paymentData = Helper::dataGet($token, 'paymentData');

        if (! is_array($paymentData) && ! is_object($paymentData)) {
            throw new InvalidArgumentException(
                'Apple Pay token has no "paymentData" object; pass the token Apple gave the browser'
                . ' (event.payment.token), not its paymentData member.'
            );
        }

        return new static(
            $merchantSessionKey,
            $clientIpAddress,
            base64_encode(json_encode(['paymentData' => $paymentData])),
            $sessionValidationToken,
            Helper::dataGet($token, 'paymentData.header.applicationData'),
            Helper::dataGet($token, 'paymentMethod.displayName'),
            Helper::dataGet($token, 'paymentMethod.type')
        );
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
            (string)Helper::dataGet($data, 'merchantSessionKey'),
            (string)Helper::dataGet($data, 'clientIpAddress'),
            // "payload" was the (incorrect) name used by earlier releases of this package.
            (string)(Helper::dataGet($data, 'paymentData') ?? Helper::dataGet($data, 'payload')),
            Helper::dataGet($data, 'sessionValidationToken'),
            Helper::dataGet($data, 'applicationData'),
            Helper::dataGet($data, 'displayName'),
            Helper::dataGet($data, 'paymentMethodType')
        );
    }

    /**
     * Return the complete object data for serialized storage and API submission.
     */
    public function jsonSerialize(): mixed
    {
        $applePay = [
            'merchantSessionKey' => $this->merchantSessionKey,
            'clientIpAddress' => $this->clientIpAddress,
            'paymentData' => $this->paymentData,
        ];

        foreach (['sessionValidationToken', 'applicationData', 'displayName', 'paymentMethodType'] as $optional) {
            if ($this->$optional !== null) {
                $applePay[$optional] = $this->$optional;
            }
        }

        return ['applePay' => $applePay];
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
     * The base64-encoded Apple paymentData.
     */
    public function getPaymentData(): string
    {
        return $this->paymentData;
    }

    public function getSessionValidationToken(): ?string
    {
        return $this->sessionValidationToken;
    }

    public function getApplicationData(): ?string
    {
        return $this->applicationData;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function getPaymentMethodType(): ?string
    {
        return $this->paymentMethodType;
    }

    /**
     * Clone with the session validation token set (Opayo-managed certificate flow).
     */
    public function withSessionValidationToken(string $sessionValidationToken): static
    {
        $clone = clone $this;
        $clone->sessionValidationToken = $sessionValidationToken;
        return $clone;
    }
}
