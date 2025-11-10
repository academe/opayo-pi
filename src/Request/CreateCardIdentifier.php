<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

/**
 * Request message for sending card details to Sage Pay to get a
 * Card Identifier.
 *
 * This will normally only be done on the server side when testing.
 *
 * With the right PCI compliance, the details could be captured
 * by the merchant site and sent direct to SagePay server-to-server,
 * similar to how Sage Pay Direct would.
 */

use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Response\SessionKey;
use Academe\Opayo\Pi\Security\SensitiveValue;

class CreateCardIdentifier extends AbstractRequest
{
    protected array $resource_path = ['card-identifiers'];
    private readonly SensitiveValue $cardholderName;
    private readonly SensitiveValue $cardNumber;
    private readonly SensitiveValue $expiryDate;
    private readonly SensitiveValue $securityCode;

    /**
     * @param Endpoint $endpoint
     * @param Auth $auth
     * @param SessionKey|string $sessionKey Any object that casts to sessionKey string is suitable.
     * @param string $cardholderName
     * @param string $cardNumber
     * @param string $expiryDate
     * @param string|null $securityCode
     */
    public function __construct(
        Endpoint $endpoint,
        Auth $auth,
        private readonly string $sessionKey,
        string $cardholderName,
        string $cardNumber,
        string $expiryDate,
        ?string $securityCode = null
    ) {
        $this->setEndpoint($endpoint);
        $this->setAuth($auth);

        $this->cardholderName = new SensitiveValue($cardholderName);
        $this->cardNumber = new SensitiveValue($cardNumber);
        $this->expiryDate = new SensitiveValue($expiryDate);
        $this->securityCode = new SensitiveValue($securityCode);
    }

    public function getCardholderName(): ?string
    {
        return $this->cardholderName?->peek();
    }

    public function getCardNumber(): ?string
    {
        return $this->cardNumber?->peek();
    }

    public function getExpiryDate(): ?string
    {
        return $this->expiryDate?->peek();
    }

    public function getSecurityCode(): ?string
    {
        return $this->securityCode?->peek();
    }

    public function jsonSerialize(): mixed
    {
        $data = $this->jsonSerializePeek();

        array_walk_recursive($data, function (&$item, $key) {
            if (is_string($item)) {
                $item = str_repeat('*', strlen($item));
            }
        });

        return $data;
    }

    public function jsonSerializePeek(): array
    {
        $data = [
            'cardDetails' => [
                'cardholderName' => $this->getCardholderName(),
                'cardNumber' => $this->getCardNumber(),
                'expiryDate' => $this->getExpiryDate(),
            ],
        ];

        if (!empty($this->getSecurityCode())) {
            $data['cardDetails']['securityCode'] = $this->getSecurityCode();
        }

        return $data;
    }

    public function getAuthHeaders(): array
    {
        return [
            'Authorization' => ['Bearer ' . $this->sessionKey],
        ];
    }
}
