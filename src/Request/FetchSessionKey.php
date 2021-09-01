<?php

namespace Academe\Opayo\Pi\Request;

/**
 * The request for fetching a copy of a session key, to check its validity.
 * The response will be a SessionKeyResponse message.
 */

use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Response\SessionKey as SessionKeyResponse;

class FetchSessionKey extends AbstractRequest
{
    protected $resource_path = ['merchant-session-keys', '{merchantSessionKey}'];

    /**
     * @var string The session key.
     */
    protected $sessionKey;

    /**
     * @var string This message is a GET request
     */
    protected $method = 'GET';

    /**
     * Supply the previously provided SessionKeyResponse for validation.
     * @param Endpoint $endpoint
     * @param SessionKeyResponse|string $sessionKey
     */
    public function __construct(Endpoint $endpoint, $sessionKey)
    {
        $this->endpoint = $endpoint;

        // We only want the session key string.
        $this->sessionKey = (string)$sessionKey;
    }

    /**
     * The merchantSessionKey will be sent as a URL parameter.
     * This message to SagePay has no body otherwise, and no authorisation is required.
     * @return null|string
     */
    public function getMerchantSessionKey()
    {
        return $this->sessionKey;
    }

    /**
     * This message has no body.
     */
    public function jsonSerialize()
    {
    }

    /**
     * This message has no authentication headers.
     * @return array
     */
    public function getHeaders()
    {
        return [];
    }
}
