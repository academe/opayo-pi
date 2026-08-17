<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Model;

use JsonSerializable;

/**
 * Credential on file object, required for reusing cards.
 *
 * @see https://developer-eu.elavon.com/docs/opayo/credential-file-0
 */

class CredentialType implements JsonSerializable
{
    public const COF_USAGE_FIRST = 'First';
    public const COF_USAGE_SUBSEQUENT = 'Subsequent';

    public const INITIATED_TYPE_CONSUMER_INITIATED = 'CIT';
    public const INITIATED_TYPE_MERCHANT_INITIATED = 'MIT';

    public const MIT_TYPE_RECURRING = 'Recurring';
    public const MIT_TYPE_INSTALMENT = 'Instalment';
    public const MIT_TYPE_UNSCHEDULED = 'Unscheduled';
    public const MIT_TYPE_INCREMENTAL = 'Incremental';
    public const MIT_TYPE_DELAYEDCHARGE = 'DelayedCharge';
    public const MIT_TYPE_NOSHOW = 'NoShow';
    public const MIT_TYPE_REAUTHORISATION = 'Reauthorisation';
    public const MIT_TYPE_RESUBMISSION = 'Resubmission';

    public function __construct(
        protected readonly string $cofUsage,
        protected readonly string $initiatedType,
        protected readonly ?string $mitType = null,
        protected readonly ?string $recurringExpiry = null,
        protected readonly ?int $recurringFrequency = null,
        protected readonly ?int $purchaseInstalData = null
    ) {
    }

    public static function createForNewReusableCard(): static
    {
        return new self(
            self::COF_USAGE_FIRST,
            self::INITIATED_TYPE_CONSUMER_INITIATED,
        );
    }

    public static function createForCustomerReusingCard(): static
    {
        return new self(
            self::COF_USAGE_SUBSEQUENT,
            self::INITIATED_TYPE_CONSUMER_INITIATED,
            self::MIT_TYPE_UNSCHEDULED
        );
    }

    public static function createForMerchantReusingCard(): static
    {
        return new self(
            self::COF_USAGE_SUBSEQUENT,
            self::INITIATED_TYPE_MERCHANT_INITIATED,
            self::MIT_TYPE_UNSCHEDULED
        );
    }

    /**
     * For a Repeat transaction the gateway only accepts cofUsage
     * "Subsequent" and initiatedType "MIT". recurringExpiry (YYYYMMDD, the
     * date of the last scheduled payment) and recurringFrequency (in days)
     * are required when the mitType is Recurring or Instalment.
     */
    public static function createForRepeatPayment(
        string $mitType = self::MIT_TYPE_UNSCHEDULED,
        ?string $recurringExpiry = null,
        ?int $recurringFrequency = null,
        ?int $purchaseInstalData = null
    ): static {
        return new self(
            self::COF_USAGE_SUBSEQUENT,
            self::INITIATED_TYPE_MERCHANT_INITIATED,
            $mitType,
            $recurringExpiry,
            $recurringFrequency,
            $purchaseInstalData
        );
    }

    /**
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        $attributes = [
            'cofUsage' => $this->cofUsage,
            'initiatedType' => $this->initiatedType,
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
