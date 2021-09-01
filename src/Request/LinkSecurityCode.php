<?php

namespace Academe\Opayo\Pi\Request;

/**
 * Request for linking a security code to a saved cardIdentifier.
 * Allows a security code to be captured and linked to a saved card identifier
 * for just one transaction, for additional security. Sage Pay will then throw it
 * away.
 */

use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Response\SessionKey as SessionKeyResponse;
use Academe\Opayo\Pi\Security\SensitiveValue;

class LinkSecurityCode extends AbstractRequest
{
    protected $resource_path = ['card-identifiers', '{cardIdentifier}', 'security-code'];

    protected $cardIdentifier;
    protected $sessionKey;

    /**
     * @var A sensitive value.
     */
    protected $securityCode;

    /**
     * @param Endpoint $endpoint
     * @param Auth $auth
     * @param SessionKeyResponse|string $sessionKey
     * @param Response\CardIdentifier|string $cardIdentifier
     * @param string $securityCode
     */
    public function __construct(
        Endpoint $endpoint,
        Auth $auth,
        $sessionKey,
        $cardIdentifier,
        $securityCode
    ) {
        $this->setEndpoint($endpoint);
        $this->setAuth($auth);

        $this->sessionKey = (string)$sessionKey;
        $this->cardIdentifier = (string)$cardIdentifier;

        $this->securityCode = new SensitiveValue($securityCode);
    }

    /**
     * @return SensitiveValue|mixed
     */
    public function getSecurityCode()
    {
        return $this->securityCode ? $this->securityCode->peek() : $this->securityCode;
    }

    /**
     *
     */
    public function getCardIdentifier()
    {
        return $this->cardIdentifier;
    }

    /**
     * Get the message body data for serializing.
     * @return array
     */
    public function jsonSerialize()
    {
        $return = [
            'securityCode' => $this->getSecurityCode(),
        ];

        return $return;
    }

    /**
     * Get the message header data as an array.
     * TODO: Move the details of this to the abstract, as it is used in several places,
     * and remove it from the Response\SessionKey class as it has nothing to do with responses.
     * @return array
     */
    public function getHeaders()
    {
        return [
            'Authorization' => 'Bearer ' . $this->sessionKey,
        ];
    }
}
