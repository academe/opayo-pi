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
 *
 * Prefer ApplePaySession::fromHttpResponse($response) over the generic
 * ResponseFactory for this call: it returns this class or an ErrorCollection
 * (4xx), and does not depend on sniffing the body shape.
 */

class ApplePaySession extends AbstractResponse
{
    // Same vocabulary as transactions; single source of values.
    public const STATUS_OK = AbstractTransaction::STATUS_OK;
    public const STATUS_MALFORMED = AbstractTransaction::STATUS_MALFORMED;
    public const STATUS_INVALID = AbstractTransaction::STATUS_INVALID;
    public const STATUS_ERROR = AbstractTransaction::STATUS_ERROR;

    protected ?string $statusCode = null;
    protected ?string $statusDetail = null;

    /**
     * Millisecond Unix timestamps. Opayo's reference shows them as strings,
     * Apple's own merchant session uses numbers; they are kept exactly as
     * received and passed back to Safari unchanged.
     */
    protected int|string|null $epochTimestamp = null;
    protected int|string|null $expiresAt = null;

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
     * Is this data an Apple Pay session response (successful or not)?
     * Used by the ResponseFactory. A successful session carries the session
     * identifiers; a failed one is a bare status block (Invalid/Malformed with
     * a statusCode) and no transactionId. A failed session with status "Error"
     * is indistinguishable from a bare 3D Secure status by shape, which is why
     * fromHttpResponse() on this class is the preferred entry point.
     */
    public static function isResponse(mixed $data): bool
    {
        if (! is_array($data) && ! is_object($data)) {
            return false;
        }

        if (Helper::dataGet($data, 'merchantSessionIdentifier') || Helper::dataGet($data, 'sessionValidationToken')) {
            return true;
        }

        $status = Helper::dataGet($data, 'status');

        return in_array($status, [static::STATUS_INVALID, static::STATUS_MALFORMED], true)
            && Helper::dataGet($data, 'statusCode') !== null
            && Helper::dataGet($data, 'transactionId') === null;
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

    public function getEpochTimestamp(): int|string|null
    {
        return $this->epochTimestamp;
    }

    public function getExpiresAt(): int|string|null
    {
        return $this->expiresAt;
    }

    /**
     * The merchant session object to return to the browser for
     * ApplePaySession.completeMerchantValidation(). Field names, case and
     * value types are as received; status, statusDetail and
     * sessionValidationToken are deliberately omitted (the token must never
     * reach the browser).
     *
     * @return array<string, int|string>
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
        $status = array_filter([
            'status' => $this->status,
            'statusCode' => $this->statusCode,
            'statusDetail' => $this->statusDetail,
            'sessionValidationToken' => $this->sessionValidationToken,
        ], fn ($v) => $v !== null);

        return $status + $this->getMerchantSession();
    }
}
