<?php namespace Academe\SagePayJs\Message;

/**
 * Value object to hold the card identifier, returned by SagePay.
 * Reasonable validation is done at creation.
 */

use DateTime;
use DateTimeZone;

use Exception;
use UnexpectedValueException;

class CardIdentifierResponse extends AbstractMessage
{
    protected $cardIdentifier;
    protected $expiry;
    protected $cardType;

    public function __construct($cardIdentifier, $expiry, $cardType)
    {
        $this->cardIdentifier = $cardIdentifier;
        $this->expiry = $this->parseDateTime($expiry);
        $this->cardType = $cardType;
    }

    public function getCardIdentifier()
    {
        return $this->cardIdentifier;
    }

    public function getExpiry()
    {
        return $this->expiry;
    }

    public function getCardType()
    {
        return $this->cardType;
    }

    public function isExpired()
    {
        // Use the default system timezone; the DateTime comparison
        // operation will handle any timezone conversions.

        $time_now = new DateTime();

        return ! isset($this->expiry) || $time_now > $this->expiry;
    }

    public static function fromData($data)
    {
        $cardIdentifier = static::structureGet($data, 'cardIdentifier');
        $expiry = static::structureGet($data, 'expiry');
        $cardType = static::structureGet($data, 'cardType');

        return new static($cardIdentifier, $expiry, $cardType);
    }
}