<?php

namespace Academe\Opayo\Pi\Request;

/**
 * Request message for sending card details to Sage Pay to get a
 * Card Identifier.
 *
 * This will normally only be done on the server side when testing.
 *
 * With the right PCI compliance, the details could be captured
 * by the merchant site and sent direct to SagePay server-to-server,
 * similat to how Sage Pay Direct would.
 */

use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Response\SessionKey;
use Academe\Opayo\Pi\Security\SensitiveValue;

class CreateCardIdentifier extends AbstractRequest
{
    protected $resource_path = ['card-identifiers'];

    protected $sessionKey;

    // Store card details as sensitive information.
    // This won't protect us from JSON serialisation, since that function is needed
    // for constructing messages, but should help protect from other types of serialisation.

    protected $cardholderName;
    protected $cardNumber;
    protected $expiryDate;
    protected $securityCode;

    /**
     * @param Endpoint $endpoint
     * @param Auth $auth
     * @param SessionKey|string $sessionKey Any object that casts to sessionKey string is suitable.
     * @param $cardholderName
     * @param $cardNumber
     * @param $expiryDate
     * @param null $securityCode
     */
    public function __construct(
        Endpoint $endpoint,
        Auth $auth,
        $sessionKey,
        //
        $cardholderName,
        $cardNumber,
        $expiryDate,
        $securityCode = null
    ) {
        // Access data.
        $this->setEndpoint($endpoint);
        $this->setAuth($auth);
        $this->sessionKey = (string)$sessionKey;

        // Card details.
        $this->cardholderName = new SensitiveValue($cardholderName);
        $this->cardNumber = new SensitiveValue($cardNumber);
        $this->expiryDate = new SensitiveValue($expiryDate);
        $this->securityCode = new SensitiveValue($securityCode);
    }

    /**
     * @return SensitiveValue|mixed
     */
    public function getCardholderName()
    {
        return $this->cardholderName ? $this->cardholderName->peek() : $this->cardholderName;
    }

    /**
     * @return SensitiveValue|mixed
     */
    public function getCardNumber()
    {
        return $this->cardNumber ? $this->cardNumber->peek() : $this->cardNumber;
    }

    /**
     * @return SensitiveValue|mixed
     */
    public function getExpiryDate()
    {
        return $this->expiryDate ? $this->expiryDate->peek() : $this->expiryDate;
    }

    /**
     * @return SensitiveValue|mixed
     */
    public function getSecurityCode()
    {
        return $this->securityCode ? $this->securityCode->peek() : $this->securityCode;
    }

    /**
     * Protect this class from direct JSON serialisation.
     * Replace all card detail characters with asterisks.
     * @return array
     */
    public function jsonSerialize()
    {
        $data = $this->jsonSerializePeek();

        array_walk_recursive($data, function (&$item, $key) {
            if (is_string($item)) {
                $item = str_repeat('*', strlen($item));
            }
        });

        return $data;
    }

    /**
     * Get the message body data for serializing.
     * This is the explicit JSON serialisation method, not called up during debug.
     * @return array
     */
    public function jsonSerializePeek()
    {
        $data = [
            'cardDetails' => [
                'cardholderName' => $this->getCardholderName(),
                'cardNumber' => $this->getCardNumber(),
                'expiryDate' => $this->getExpiryDate(),
            ],
        ];

        // The security code is optional, so only provide it if it has been set.

        if (! empty($this->getSecurityCode())) {
            $data['cardDetails']['securityCode'] = $this->getSecurityCode();
        }

        return $data;
    }

    /**
     * Get the message header data as an array.
     * This request does not use the HTTP Basic Auth, but the temporary session
     * key token instead. This is because it will accessible to end users, and
     * the secure integration key and password cannot be exposed here.
     * 
     * @return array
     */
    public function getAuthHeaders()
    {
        return [
            'Authorization' => ['Bearer ' . $this->sessionKey],
        ];
    }
}
