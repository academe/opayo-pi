<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

/**
 * The request for fetching a copy of a session key, to check its validity.
 * The response will be a SessionKeyResponse message.
 */

use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Response\SessionKey as SessionKeyResponse;

class FetchSessionKey extends AbstractRequest
{
    protected array $resource_path = ['merchant-session-keys', '{merchantSessionKey}'];
    protected string $method = 'GET';

    /**
     * Supply the previously provided SessionKeyResponse for validation.
     * @param Endpoint $endpoint
     * @param SessionKeyResponse|string $sessionKey The session key
     */
    public function __construct(
        Endpoint $endpoint,
        protected readonly string $sessionKey
    ) {
        $this->endpoint = $endpoint;
    }

    /**
     * The merchantSessionKey will be sent as a URL parameter.
     * This message to SagePay has no body otherwise, and no authorisation is required.
     * @return string
     */
    public function getMerchantSessionKey(): string
    {
        return $this->sessionKey;
    }

    /**
     * This message has no body.
     */
    public function jsonSerialize(): mixed
    {
        return null;
    }

    /**
     * This message has no authentication headers.
     * @return array
     */
    public function getHeaders(): array
    {
        return [];
    }
}
