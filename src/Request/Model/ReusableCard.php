<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Model;

use Academe\Opayo\Pi\Response\CardIdentifier;
use Academe\Opayo\Pi\Response\SessionKey;
use Academe\Opayo\Pi\Helper;

/**
 * Card object to be passed to SagePay for payment of a transaction.
 * This message contains a card identifier linked to a session key.
 * There are two times that would be used:
 * 1. When first using the card that has been tokenised at the front end.
 * 2. When reusing a card that has been linked to a CVV at the front end.
 */

class ReusableCard extends AbstractCard
{
    /**
     * Card constructor.
     *
     * @param CardIdentifier|string $cardIdentifier
     */
    public function __construct(CardIdentifier|string $cardIdentifier)
    {
        $this->cardIdentifier = (string)$cardIdentifier;
    }

    /**
     * Construct an instance from stored data (e.g. JSON serialised object).
     *
     * @param array|object|string $data
     * @return static
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
            Helper::dataGet($data, 'cardIdentifier')
        );
    }

    /**
     * Return the complete object data for serialized storage.
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        return [
            'card' => [
                'cardIdentifier' => $this->cardIdentifier,
                'reusable' => true,
            ],
        ];
    }
}
