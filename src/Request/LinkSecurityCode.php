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
     * Get the message header data as an array.
     * TODO: Move the details of this to the abstract, as it is used in several places,
     * and remove it from the Response\SessionKey class as it has nothing to do with responses.
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->sessionKey,
        ];
    }
}
