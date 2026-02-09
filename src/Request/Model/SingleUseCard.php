<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Model;

use Academe\Opayo\Pi\Helper;
use Academe\Opayo\Pi\Response\SessionKey;
use Academe\Opayo\Pi\Response\CardIdentifier;

/**
 * Card object to be passed to SagePay for payment of a transaction.
 * This message contains a card identifier linked to a session key.
 * There are two times that would be used:
 * 1. When first using the card that has been tokenised at the front end.
 * 2. When reusing a card that has been linked to a CVV at the front end.
 */

class SingleUseCard extends AbstractCard
{
    /**
     * Flag to indicate whether the cards should be saved for reuse.
     */
    protected ?bool $save = null;

    /**
     * Card constructor.
     *
     * @param SessionKey|string $sessionKey
     * @param CardIdentifier|string $cardIdentifier
     * @param bool|null $save True so (re)save this identifier as a card token for future use.
     */
    public function __construct(
        SessionKey|string $sessionKey,
        CardIdentifier|string $cardIdentifier,
        ?bool $save = null
    ) {
        $this->sessionKey = (string)$sessionKey;
        $this->cardIdentifier = (string)$cardIdentifier;

        if (isset($save)) {
            $this->save = $save;
        }
    }

    /**
     * Construct an instance from stored data (e.g. JSON serialised object).
     */
    public static function fromData(array|object|string $data): static
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
     * @return self
     */
    public function withSave(bool $save = true): self
    {
        $clone = clone $this;

        $clone->save = $save;

        return $clone;
    }

    /**
     * Return the complete object data for serialized storage.
     * @return array
     */
    public function jsonSerialize(): mixed
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
