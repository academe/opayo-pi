<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response\Model;

use Academe\Opayo\Pi\Helper;
use JsonSerializable;

/**
 * Abstract Card details.
 */

class Card implements JsonSerializable
{
    /**
     * Card constructor.
     *
     * @param string|null $cardType
     * @param string|null $lastFourDigits
     * @param string|null $expiryDate MMYY format (TODO: validate MMYY)
     * @param string|null $cardIdentifier Tokenised card
     * @param bool|null $reusable Flag indicates this is a reusable card identifier
     */
    public function __construct(
        protected readonly ?string $cardType = null,
        protected readonly ?string $lastFourDigits = null,
        protected readonly ?string $expiryDate = null,
        protected readonly ?string $cardIdentifier = null,
        protected readonly bool $reusable = false
    ) {
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
            Helper::dataGet($data, 'cardType'),
            Helper::dataGet($data, 'lastFourDigits'),
            Helper::dataGet($data, 'expiryDate'),
            Helper::dataGet($data, 'cardIdentifier'),
            // Absent in some responses, e.g. for a Repeat transaction.
            Helper::dataGet($data, 'reusable', false)
        );
    }

    /**
     * @return array<string, array<string, string|bool>>
     */
    public function getData(): array
    {
        $message = ['card' => []];

        if ($this->cardType !== null) {
            $message['card']['cardType'] = $this->cardType;
        }

        if ($this->lastFourDigits !== null) {
            $message['card']['lastFourDigits'] = $this->lastFourDigits;
        }

        if ($this->expiryDate !== null) {
            $message['card']['expiryDate'] = $this->expiryDate;
        }

        if ($this->cardIdentifier !== null) {
            $message['card']['cardIdentifier'] = $this->cardIdentifier;
        }

        if ($this->reusable !== null) {
            $message['card']['reusable'] = $this->reusable;
        }

        return $message;
    }

    /**
     * Serialisation for storage.
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        return $this->getData();
    }

    /**
     * Tells you if this is a reusable card token.
     *
     * @return bool
     */
    public function isReusable(): bool
    {
        return $this->reusable === true;
    }

    /**
     * Content of the reusable flag.
     *
     * @return bool
     */
    public function getReusable(): bool
    {
        return $this->reusable;
    }

    /**
     * Getter for the type of credit card.
     * There is no definitive list of card types, but "Visa", "MasterCard" and
     * "American Express" are given as examples.
     * @return string|null Null if no card type present or not a card
     */
    public function getCardType(): ?string
    {
        return $this->cardType;
    }

    /**
     * Getter for the last four digits of the credit card.
     * @return string|null Null if no digits present or not a card
     */
    public function getLastFourDigits(): ?string
    {
        return $this->lastFourDigits;
    }

    public function getCardIdentifier(): ?string
    {
        return $this->cardIdentifier;
    }

    /**
     * Getter for the raw expiry date of the credit card.
     * @return string|null Format MMYY
     */
    public function getExpiryDate(): ?string
    {
        return $this->expiryDate;
    }

    /**
     * @return string|null Month number, format MM (leading zero)
     */
    public function getExpiryMonth(): ?string
    {
        $expiry = $this->getExpiryDate();

        if (! preg_match('/[0-9]{4}/', $expiry)) {
            return null;
        }

        return substr($expiry, 0, 2);
    }

    /**
     * No attempt is made to expand the year into four digits.
     * @return string|null Year number, format YY
     */
    public function getExpiryYear(): ?string
    {
        $expiry = $this->getExpiryDate();

        if (! preg_match('/[0-9]{4}/', $expiry)) {
            return null;
        }

        return substr($expiry, 2, 2);
    }
}
