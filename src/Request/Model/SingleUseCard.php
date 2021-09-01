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

class SingleUseCard extends AbstractCard
{
    /**
     * Flag to indicaste whether the cards should be saved for reuse.
     *
     * @var bool|null
     */
    protected $save;

    /**
     * Card constructor.
     *
     * @param Academe\Opayo\Pi\Response\SessionKey|string $sessionKey
     * @param Academe\Opayo\Pi\Response\CardIdentifier|string $cardIdentifier
     * @param boolean $save True so (re)save this identifier as a card token for future use.
     */
    public function __construct($sessionKey, $cardIdentifier, $save = null)
    {
        $this->sessionKey = (string)$sessionKey;
        $this->cardIdentifier = (string)$cardIdentifier;

        if (isset($save)) {
            $this->save = (bool)$save;
        }
    }

    /**
     * Construct an instance from stored data (e.g. JSON serialised object).
     */
    public static function fromData($data)
    {
        // For convenience.
        if (is_string($data)) {
            $data = json_decode($data);
        }

        // The data will normally be in a "card" wrapper element.
        // Remove it to make processing easier.
        if ($card = Helper::dataGet($data, 'card')) {
            $data = $card;
        }

        return new static(
            Helper::dataGet($data, 'merchantSessionKey'),
            Helper::dataGet($data, 'cardIdentifier')
        );
    }

    /**
     * Sets or resets the save flag.
     * Only valid for unsaved cards, i.e. the first use "SessionCard".
     *
     * @returnb self
     */
    public function withSave($save = true)
    {
        $clone = clone $this;

        $clone->save = (bool)$save;

        return $clone;
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
            ],
        ];

        if ($this->save !== null) {
            $message['card']['save'] = $this->save;
        }

        return $message;
    }
}
