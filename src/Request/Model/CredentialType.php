<?php

namespace Academe\Opayo\Pi\Request\Model;

/**
 * Credential on file object, required for reusing cards.
 *
 * @see https://developer-eu.elavon.com/docs/opayo/credential-file-0
 */

use JsonSerializable;

class CredentialType implements JsonSerializable
{
    const COF_USAGE_FIRST = 'First';
    const COF_USAGE_SUBSEQUENT = 'Subsequent';

    const INITIATED_TYPE_CONSUMER_INITIATED = 'CIT';
    const INITIATED_TYPE_MERCHANT_INITIATED = 'MIT';

    const MIT_TYPE_RECURRING = 'Recurring';
    const MIT_TYPE_INSTALMENT = 'Instalment';
    const MIT_TYPE_UNSCHEDULED = 'Unscheduled';
    const MIT_TYPE_INCREMENTAL = 'Incremental';
    const MIT_TYPE_DELAYEDCHARGE = 'DelayedCharge';
    const MIT_TYPE_NOSHOW = 'NoShow';
    const MIT_TYPE_REAUTHORISATION = 'Reauthorisation';
    const MIT_TYPE_RESUBMISSION = 'Resubmission';

    protected $cofUsage;
    protected $initiatedType;
    protected $mitType;
    protected $recurringExpiry;
    protected $recurringFrequency;
    protected $purchaseInstalData;

    public function __construct(
        $cofUsage,
        $initiatedType,
        $mitType = null,
        $recurringExpiry = null,
        $recurringFrequency = null,
        $purchaseInstalData = null
    ) {
        $this->cofUsage = $cofUsage;
        $this->initiatedType = $initiatedType;
        $this->mitType = $mitType;
        $this->recurringExpiry = $recurringExpiry;
        $this->recurringFrequency = $recurringFrequency;
        $this->purchaseInstalData = $purchaseInstalData;
    }

    public static function createForNewReusableCard()
    {
        return new self(
            self::COF_USAGE_FIRST,
            self::INITIATED_TYPE_CONSUMER_INITIATED,
        );
    }

    public static function createForCustomerReusingCard()
    {
        return new self(
            self::COF_USAGE_SUBSEQUENT,
            self::INITIATED_TYPE_CONSUMER_INITIATED,
            self::MIT_TYPE_UNSCHEDULED
        );
    }

    public static function createForMerchantReusingCard()
    {
        return new self(
            self::COF_USAGE_SUBSEQUENT,
            self::INITIATED_TYPE_MERCHANT_INITIATED,
            self::MIT_TYPE_UNSCHEDULED
        );
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        $attributes = [
            'cofUsage' => $this->cofUsage,
            'initiatedType' => $this->initiatedType,
            'mitType' => $this->mitType,
        ];

        if ($this->mitType !== null) {
            $attributes['mitType'] = $this->mitType;
        }

        if ($this->recurringExpiry !== null) {
            $attributes['recurringExpiry'] = $this->recurringExpiry;
        }

        if ($this->recurringFrequency !== null) {
            $attributes['recurringFrequency'] = $this->recurringFrequency;
        }

        if ($this->purchaseInstalData !== null) {
            $attributes['purchaseInstalData'] = $this->purchaseInstalData;
        }

        return $attributes;
    }
}