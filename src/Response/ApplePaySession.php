<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response;

use Academe\Opayo\Pi\Helper;

/**
 * The Apple Pay merchant session returned by POST /applepay/sessions
 * (Request\CreateApplePaySession), for the Opayo-managed certificate flow.
 *
 * Two things come out of it:
 *  - getMerchantSession(): the object to hand back to Safari for
 *    ApplePaySession.completeMerchantValidation() (status, statusDetail and
 *    sessionValidationToken removed, field names as Apple expects them);
 *  - getSessionValidationToken(): to be sent with the transaction in
 *    Request\Model\ApplePayPayment, exactly as received.
 */

class ApplePaySession extends AbstractResponse
{
    public const STATUS_OK = 'Ok';
    public const STATUS_MALFORMED = 'Malformed';
    public const STATUS_INVALID = 'Invalid';
    public const STATUS_ERROR = 'Error';

    protected ?string $statusCode = null;
    protected ?string $statusDetail = null;
    protected ?string $epochTimestamp = null;
    protected ?string $expiresAt = null;
    protected ?string $merchantSessionIdentifier = null;
    protected ?string $nonce = null;
    protected ?string $merchantIdentifier = null;
    protected ?string $domainName = null;
    protected ?string $displayName = null;
    protected ?string $signature = null;
    protected ?string $sessionValidationToken = null;

    protected function setData(mixed $data): mixed
    {
        $this->status = Helper::dataGet($data, 'status');
        $this->statusCode = Helper::dataGet($data, 'statusCode');
        $this->statusDetail = Helper::dataGet($data, 'statusDetail');

        // The API reference spells this "epochTimeStamp"; the integration guide
        // and Apple's own session object use "epochTimestamp". Accept either.
        $this->epochTimestamp = Helper::dataGet($data, 'epochTimestamp')
            ?? Helper::dataGet($data, 'epochTimeStamp');

        $this->expiresAt = Helper::dataGet($data, 'expiresAt');
        $this->merchantSessionIdentifier = Helper::dataGet($data, 'merchantSessionIdentifier');
        $this->nonce = Helper::dataGet($data, 'nonce');
        $this->merchantIdentifier = Helper::dataGet($data, 'merchantIdentifier');
        $this->domainName = Helper::dataGet($data, 'domainName');
        $this->displayName = Helper::dataGet($data, 'displayName');
        $this->signature = Helper::dataGet($data, 'signature');
        $this->sessionValidationToken = Helper::dataGet($data, 'sessionValidationToken');

        return $this;
    }

    /**
     * True when Opayo created the session ("Ok").
     */
    public function isSuccess(): bool
    {
        return $this->getStatus() === static::STATUS_OK;
    }

    public function getStatusCode(): ?string
    {
        return $this->statusCode;
    }

    public function getStatusDetail(): ?string
    {
        return $this->statusDetail;
    }

    /**
     * The token to send with the transaction (ApplePayPayment), exactly as received.
     */
    public function getSessionValidationToken(): ?string
    {
        return $this->sessionValidationToken;
    }

    public function getMerchantSessionIdentifier(): ?string
    {
        return $this->merchantSessionIdentifier;
    }

    public function getNonce(): ?string
    {
        return $this->nonce;
    }

    public function getMerchantIdentifier(): ?string
    {
        return $this->merchantIdentifier;
    }

    public function getDomainName(): ?string
    {
        return $this->domainName;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }

    public function getEpochTimestamp(): ?string
    {
        return $this->epochTimestamp;
    }

    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
    }

    /**
     * The merchant session object to return to the browser for
     * ApplePaySession.completeMerchantValidation(). Field names and case are
     * as Apple requires; status, statusDetail and sessionValidationToken are
     * deliberately omitted (the token must never reach the browser).
     *
     * @return array<string, string>
     */
    public function getMerchantSession(): array
    {
        return array_filter([
            'epochTimestamp' => $this->epochTimestamp,
            'expiresAt' => $this->expiresAt,
            'merchantSessionIdentifier' => $this->merchantSessionIdentifier,
            'nonce' => $this->nonce,
            'merchantIdentifier' => $this->merchantIdentifier,
            'domainName' => $this->domainName,
            'displayName' => $this->displayName,
            'signature' => $this->signature,
        ], fn ($v) => $v !== null);
    }

    /**
     * Serialisation for storage/logging/debug (the full response, including the token).
     */
    public function jsonSerialize(): mixed
    {
        return array_filter([
            'status' => $this->status,
            'statusCode' => $this->statusCode,
            'statusDetail' => $this->statusDetail,
            'sessionValidationToken' => $this->sessionValidationToken,
        ] + $this->getMerchantSession(), fn ($v) => $v !== null);
    }
}
