<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response;

use DateTime;
use Psr\Http\Message\ResponseInterface;
use Academe\Opayo\Pi\Helper;

/**
 * Value object holding the merchant session key returned by SagePay.
 * See https://test.sagepay.com/documentation/#merchant-session-keys
 */

class SessionKey extends AbstractResponse
{
    protected ?string $merchantSessionKey = null;
    protected ?DateTime $expiry = null;

    /**
     * @param mixed $data
     * @return mixed
     */
    protected function setData(mixed $data): mixed
    {
        $this->merchantSessionKey = Helper::dataGet($data, 'merchantSessionKey');

        $expiry = Helper::dataGet($data, 'expiry');

        if (isset($expiry)) {
            $this->expiry = Helper::parseDateTime($expiry);
        }

        return $this;
    }

    /**
     * @return string|null
     */
    public function getMerchantSessionKey(): ?string
    {
        return $this->merchantSessionKey;
    }

    /**
     * When used in a further request, there is just one important part of this
     * object: the session key string.
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->getMerchantSessionKey();
    }

    /**
     * @return DateTime|null The time at which the session key will expire
     */
    public function getExpiry(): ?DateTime
    {
        return $this->expiry;
    }

    public function isExpired(): bool
    {
        // Use the default system timezone; the DateTime comparison
        // operation will handle any timezone conversions.
        // A null expiry is considered to be expired.

        $time_now = new DateTime();

        return ! isset($this->expiry) || $time_now > $this->expiry;
    }

    /**
     * @return bool True if the session key appears to be valid and usable.
     */
    public function isValid(): bool
    {
        // Check if it has expired according to the time we have.
        if ($this->isExpired()) {
            return false;
        }

        // Do we have a 404 HTTP response code recorded?
        if ($this->getHttpCode() !== null && $this->getHttpCode() === $this::NOT_FOUND) {
            return false;
        }

        // Is there even a session key set?
        if ($this->getMerchantSessionKey() === null) {
            return false;
        }

        // It has got through all the failure tests, so must be valid.
        // That doesn't mean it won't expire before it is used, or has not
        // been used the maximum number of times it can, but locally it looks
        // fine.

        return true;
    }

    /**
     * Return an array to support the generation of the hidden field in
     * the form that submits to Sage Pay (via sagepay.js). The array contains all the
     * attributes needed to create the input element.
     * TODO: make this an object that can handle its rendering too.
     *
     * @return array<string, array<string, mixed>>
     */
    public function toHtmlElements(): array
    {
        return [
            'merchantSessionKey' => [
                'name' => 'input',
                'attributes' => [
                    'type' => 'hidden',
                    'data-sagepay' => 'merchantSessionKey',
                    'value' => $this->merchantSessionKey,
                ],
            ],
        ];
    }

    /**
     * Reduce the object to an array so it can be serialised.
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        return [
            'merchantSessionKey' => $this->getMerchantSessionKey(),
            'expiry' => $this->getExpiry() ? $this->getExpiry()->format(Helper::SAGEPAY_DATE_FORMAT) : null,
        ];
    }
}
