<?php

namespace Academe\Opayo\Pi\Request\Model;

/**
 * Card object to be passed to SagePay for payment of a transaction.
 * This message contains a card identifier linked to a session key.
 * There are two times that would be used:
 * 1. When first using the card that has been tokenised at the front end.
 * 2. When reusing a card that has been linked to a CVV at the front end.
 */

use Academe\Opayo\Pi\Helper;

class ReusableCvvCard extends SingleUseCard
{
    /**
     * Card constructor.
     *
     * @param Academe\Opayo\Pi\Response\SessionKey|string $sessionKey
     * @param Academe\Opayo\Pi\Response\CardIdentifier|string $cardIdentifier
     */
    public function __construct($sessionKey, $cardIdentifier)
    {
        $this->cardIdentifier = (string)$cardIdentifier;
        $this->sessionKey = (string)$sessionKey;
    }

    /**
     * Return the complete object data for serialized storage.
     * @return array
     */
    public function jsonSerialize()
    {
        $message = [
            'card' => [
                'merchantSessionKey' => $this->sessionKey,
                'cardIdentifier' => $this->cardIdentifier,
                'reusable' => true,
            ],
        ];

        return $message;
    }
}
