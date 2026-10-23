<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Response\SessionKey as SessionKeyResponse;
use Academe\Opayo\Pi\Security\SensitiveValue;

/**
 * Request for linking a security code to a saved cardIdentifier.
 * Allows a security code to be captured and linked to a saved card identifier
 * for just one transaction, for additional security. Sage Pay will then throw it
 * away.
 */

class LinkSecurityCode extends AbstractRequest
{
    protected array $resource_path = ['card-identifiers', '{cardIdentifier}', 'security-code'];
    private readonly SensitiveValue $securityCode;

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
        protected readonly string $sessionKey,
        protected readonly string $cardIdentifier,
        string $securityCode
    ) {
        $this->setEndpoint($endpoint);
        $this->setAuth($auth);
        $this->securityCode = new SensitiveValue($securityCode);
    }

    /**
     * @return string|null
     */
    public function getSecurityCode(): ?string
    {
        return $this->securityCode?->peek();
    }

    /**
     * @return string
     */
    public function getCardIdentifier(): string
    {
        return $this->cardIdentifier;
    }

    /**
     * Get the message body data for serializing.
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        return [
            'securityCode' => $this->getSecurityCode(),
        ];
    }

    /**
     * Authorised by the merchant session key, like creating the card
     * identifier. The JSON content type comes from the shared headers.
     */
    public function getAuthHeaders(): array
    {
        return [
            'Authorization' => ['Bearer ' . $this->sessionKey],
        ];
    }
}
