<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response;

/**
 * Value object to hold the card identifier, returned by Sage Pay.
 * This is just the temporary card identifier linked to a merchant session.
 * Once it is used for the first time, it can be saved and becomes more permanent.
 */

use DateTime;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Academe\Opayo\Pi\Helper;

class CardIdentifier extends AbstractResponse
{
    protected ?string $cardIdentifier = null;
    protected ?DateTime $expiry = null;
    protected ?string $cardType = null;

    /**
     * This can be set from either a direct response from Sage Pay, or fields
     * from the drop-in form (a server request).
     * TODO: other fields can be provided by the drop-in form, such as card-type.
     * CHECKME: is it worth merging this with Response\Model\Card, since both are
     * essentially card details returned from Sage Pay?
     *
     * @param array|object $data The parsed data returned by Sage Pay.
     * @return self
     */
    protected function setData(array|object $data): self
    {
        $this->cardIdentifier = Helper::dataGet(
            $data,
            'cardIdentifier',
            Helper::dataGet($data, 'card-identifier', null)
        );

        if ($expiry = Helper::dataGet($data, 'expiry', null)) {
            $this->expiry = Helper::parseDateTime($expiry);
        }

        $this->cardType = Helper::dataGet($data, 'cardType', null);

        return $this;
    }

    /**
     * @return string|null
     */
    public function getCardIdentifier(): ?string
    {
        return $this->cardIdentifier;
    }

    /**
     * When used in a further request, there is just one important part of this
     * object: the card identifier string.
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->getCardIdentifier();
    }

    /**
     * The expiry timestamp of the card identifier resource, not the expiry date of the card.
     * @return DateTime|null
     */
    public function getExpiry(): ?DateTime
    {
        return $this->expiry;
    }

    /**
     * @return string|null
     */
    public function getCardType(): ?string
    {
        return $this->cardType;
    }

    /**
     * @return bool
     */
    public function isExpired(): bool
    {
        // Use the default system timezone; the DateTime comparison
        // operation will handle any timezone conversions.
        // Note that this does not do a remote check with the Sage Pay
        // API. We can only find out if it is really still valid by
        // attempting to use it.
        // Note that the dropin form does not provide and expiry time,
        // so will always appear to be expired.

        $time_now = new DateTime();

        return ! isset($this->expiry) || $time_now > $this->expiry;
    }

    /**
     * Reduce the object to an array so it can be serialised and stored between pages.
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        return [
            'cardIdentifier' => $this->getCardIdentifier(),
            'expiry' => $this->getExpiry() ? $this->getExpiry()->format(Helper::SAGEPAY_DATE_FORMAT) : null,
            'cardType' => $this->getCardType()
        ];
    }
}
